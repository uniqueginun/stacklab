<?php

namespace App\Support;

use App\Models\Site;

class SiteNginxConfig
{
    public function documentRoot(Site $site, bool $useCurrentSymlink = false): string
    {
        $webDirectory = $site->web_directory === '.' ? '' : '/'.trim($site->web_directory, '/');

        if ($useCurrentSymlink) {
            return $site->root_path.'/current'.$webDirectory;
        }

        return $site->root_path.$webDirectory;
    }

    public function serverBlock(Site $site, ?string $publicRoot = null): string
    {
        $domain = $site->domain;
        $publicRoot ??= $this->documentRoot($site);
        $phpSocket = '/run/php/php'.$site->php_version.'-fpm.sock';

        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name {$domain};
    root {$publicRoot};

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php index.html;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \\.php\$ {
        fastcgi_pass unix:{$phpSocket};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\\.(?!well-known).* {
        deny all;
    }
}
NGINX;
    }

    public function installScript(Site $site): string
    {
        $server = $site->server;
        $domain = $site->domain;
        $rootPath = $site->root_path;
        $publicRoot = $this->documentRoot($site);
        $marker = $site->headerMarker();
        $nginxConfig = $this->serverBlock($site, $publicRoot);

        $escapedConfig = base64_encode($nginxConfig);
        $escapedMarker = escapeshellarg($marker);
        $escapedDomain = escapeshellarg($domain);
        $escapedRoot = escapeshellarg($rootPath);
        $escapedPublic = escapeshellarg($publicRoot);
        $escapedUser = escapeshellarg($server->ssh_user);

        return <<<BASH
set -euo pipefail
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:\${PATH:-}"

DOMAIN={$escapedDomain}
ROOT={$escapedRoot}
PUBLIC_ROOT={$escapedPublic}
SSH_USER={$escapedUser}
MARKER={$escapedMarker}
CONFIG_B64={$escapedConfig}
AVAILABLE="/etc/nginx/sites-available/\${DOMAIN}"
ENABLED="/etc/nginx/sites-enabled/\${DOMAIN}"
BACKUP=""

sudo -n mkdir -p "\${PUBLIC_ROOT}"
sudo -n chown -R "\${SSH_USER}:\${SSH_USER}" "\${ROOT}"
sudo -n chmod -R a+rX "\${ROOT}"

# www-data must traverse every parent of the site root. Home dirs are often 700.
path="\${ROOT}"
while [[ "\${path}" != "/" && -n "\${path}" ]]; do
  sudo -n chmod a+x "\${path}"
  path="\$(dirname "\${path}")"
done

# Write/refresh the placeholder unless a real app index.php exists. Stale
# mini-forge-site markers from earlier deploy attempts must be overwritten.
if [[ ! -f "\${PUBLIC_ROOT}/index.php" ]]; then
  if [[ ! -f "\${PUBLIC_ROOT}/index.html" ]] || grep -q 'mini-forge-site:' "\${PUBLIC_ROOT}/index.html" 2>/dev/null; then
    printf '<!doctype html><html><body><p>%s</p></body></html>\n' "\${MARKER}" > "\${PUBLIC_ROOT}/index.html"
  fi
fi

if [[ -f "\${AVAILABLE}" ]]; then
  BACKUP="\$(mktemp)"
  sudo -n cp "\${AVAILABLE}" "\${BACKUP}"
fi

printf '%s' "\${CONFIG_B64}" | base64 -d | sudo -n tee "\${AVAILABLE}" >/dev/null
sudo -n ln -sfn "\${AVAILABLE}" "\${ENABLED}"

if ! sudo -n nginx -t; then
  if [[ -n "\${BACKUP}" && -f "\${BACKUP}" ]]; then
    sudo -n cp "\${BACKUP}" "\${AVAILABLE}"
    sudo -n nginx -t
    sudo -n systemctl reload nginx || true
  else
    sudo -n rm -f "\${ENABLED}"
  fi
  rm -f "\${BACKUP}"
  echo "NGINX_TEST_FAILED"
  exit 1
fi

sudo -n systemctl reload nginx
rm -f "\${BACKUP}"
echo "SITE_DEPLOYED:\${DOMAIN}"
BASH;
    }
}
