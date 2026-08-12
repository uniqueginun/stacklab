STEP_KEY="node.install"

changed="false"
if ! command -v node >/dev/null 2>&1; then
    mini_forge_apt_update
    mini_forge_apt_install nodejs npm
    changed="true"
fi

mini_forge_require_cmd node
node_version="$(node -v)"
mini_forge_emit "$STEP_KEY" "true" "$changed" "{\"node_version\":\"${node_version}\"}"
