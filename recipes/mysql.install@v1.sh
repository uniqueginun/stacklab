STEP_KEY="mysql.install"

changed="false"
if ! command -v mysqld >/dev/null 2>&1 && ! command -v mysql >/dev/null 2>&1; then
    mini_forge_apt_update
    mini_forge_apt_install mariadb-server
    changed="true"
fi

sudo -n systemctl enable --now mariadb || sudo -n systemctl enable --now mysql || true

# Keep local root on unix_socket so `sudo mysql` remains usable for Mini-Forge recipes.
# App databases must use dedicated users — never reuse root with a password for admin.
if sudo -n mysql -e "SELECT 1" >/dev/null 2>&1; then
    sudo -n mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA unix_socket; FLUSH PRIVILEGES;" || true
elif mysql -e "SELECT 1" >/dev/null 2>&1; then
    mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA unix_socket; FLUSH PRIVILEGES;" || true
fi

mini_forge_emit "$STEP_KEY" "true" "$changed" "{}"
