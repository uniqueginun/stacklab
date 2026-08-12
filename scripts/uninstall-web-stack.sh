#!/usr/bin/env bash
# Wipe Nginx / PHP / MariaDB(MySQL) so the VM can be re-provisioned from scratch.
# Matches what stacklab recipes install: nginx, mariadb-server, php*-{cli,fpm,...}
#
# Usage (on the VM):
#   sudo bash uninstall-web-stack.sh
#   sudo bash uninstall-web-stack.sh --yes
#   sudo REMOVE_SITE_ROOTS=1 bash uninstall-web-stack.sh --yes
#
# Optional env:
#   REMOVE_SITE_ROOTS=1  also delete /home/*/domain-style site dirs (destructive)
#   SITE_USER=othman    only used with REMOVE_SITE_ROOTS (default: all home dirs)

set -euo pipefail

export DEBIAN_FRONTEND=noninteractive
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run as root: sudo bash $0" >&2
  exit 1
fi

ASSUME_YES=0
for arg in "$@"; do
  case "$arg" in
    -y|--yes) ASSUME_YES=1 ;;
    -h|--help)
      sed -n '2,14p' "$0"
      exit 0
      ;;
  esac
done

if [[ "${ASSUME_YES}" -ne 1 ]]; then
  cat <<'EOF'
This will permanently remove:
  - nginx (+ /etc/nginx sites-available / sites-enabled / conf)
  - PHP packages and /etc/php
  - MariaDB/MySQL packages and database data (/var/lib/mysql)
  - /etc/mini-forge (if present)

EOF
  read -r -p "Type YES to continue: " confirm
  if [[ "${confirm}" != "YES" ]]; then
    echo "Aborted."
    exit 1
  fi
fi

log() { printf '==> %s\n' "$*"; }

stop_disable() {
  local unit="$1"
  if systemctl list-unit-files "${unit}.service" >/dev/null 2>&1 \
    || systemctl status "${unit}" >/dev/null 2>&1; then
    systemctl stop "${unit}" 2>/dev/null || true
    systemctl disable "${unit}" 2>/dev/null || true
  fi
}

log "Stopping services"
stop_disable nginx
stop_disable php7.4-fpm
stop_disable php8.0-fpm
stop_disable php8.1-fpm
stop_disable php8.2-fpm
stop_disable php8.3-fpm
stop_disable php8.4-fpm
stop_disable mariadb
stop_disable mysql
stop_disable mysqld

log "Removing Nginx site configs"
if [[ -d /etc/nginx ]]; then
  rm -rf /etc/nginx/sites-enabled/*
  rm -rf /etc/nginx/sites-available/*
  # Keep package-managed snippets until purge removes /etc/nginx
  find /etc/nginx/conf.d -mindepth 1 -maxdepth 1 -exec rm -rf {} + 2>/dev/null || true
fi

log "Purging packages"
# nginx
apt-get purge -y 'nginx*' 'libnginx*' 2>/dev/null || true

# PHP (all versions the app may have installed)
apt-get purge -y \
  'php*' \
  'libapache2-mod-php*' \
  2>/dev/null || true

# MariaDB / MySQL (recipes install mariadb-server)
apt-get purge -y \
  'mariadb-*' \
  'mysql-*' \
  'default-mysql-*' \
  'libmariadb*' \
  'libmysql*' \
  2>/dev/null || true

log "Removing leftover config / data dirs"
rm -rf \
  /etc/nginx \
  /var/log/nginx \
  /var/cache/nginx \
  /etc/php \
  /var/lib/php \
  /etc/mysql \
  /etc/mariadb \
  /var/lib/mysql \
  /var/lib/mariadb \
  /var/log/mysql \
  /var/log/mariadb \
  /etc/mini-forge

log "Autoremove + clean"
apt-get autoremove -y --purge
apt-get autoclean -y

if [[ "${REMOVE_SITE_ROOTS:-0}" == "1" ]]; then
  log "Removing site document roots under home directories"
  # Only remove dirs that look like deployed sites (contain public/ or current/)
  targets=()
  if [[ -n "${SITE_USER:-}" ]]; then
    targets=("/home/${SITE_USER}")
  else
    # shellcheck disable=SC2207
    targets=($(find /home -mindepth 1 -maxdepth 1 -type d 2>/dev/null || true))
  fi

  for home in "${targets[@]}"; do
    [[ -d "${home}" ]] || continue
    find "${home}" -mindepth 1 -maxdepth 1 -type d | while read -r site_dir; do
      if [[ -d "${site_dir}/public" || -d "${site_dir}/current" || -d "${site_dir}/shared" ]]; then
        log "  rm -rf ${site_dir}"
        rm -rf "${site_dir}"
      fi
    done
  done
fi

log "Done. Verify:"
command -v nginx >/dev/null 2>&1 && echo "  WARN: nginx still present: $(command -v nginx)" || echo "  nginx: removed"
command -v php >/dev/null 2>&1 && echo "  WARN: php still present: $(command -v php)" || echo "  php: removed"
command -v mysql >/dev/null 2>&1 && echo "  WARN: mysql client still present: $(command -v mysql)" || echo "  mysql/mariadb client: removed"
command -v mysqld >/dev/null 2>&1 && echo "  WARN: mysqld still present: $(command -v mysqld)" || echo "  mysqld: removed"
[[ -d /etc/nginx ]] && echo "  WARN: /etc/nginx still exists" || echo "  /etc/nginx: gone"
[[ -d /var/lib/mysql ]] && echo "  WARN: /var/lib/mysql still exists" || echo "  /var/lib/mysql: gone"

echo
echo "Re-provision from the app when ready."
