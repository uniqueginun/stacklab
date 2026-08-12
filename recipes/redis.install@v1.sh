STEP_KEY="redis.install"

changed="false"
if ! command -v redis-server >/dev/null 2>&1; then
    mini_forge_apt_update
    mini_forge_apt_install redis-server
    changed="true"
fi

sudo -n systemctl enable --now redis-server || sudo -n systemctl enable --now redis || true
mini_forge_emit "$STEP_KEY" "true" "$changed" "{}"
