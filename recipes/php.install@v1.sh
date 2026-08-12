STEP_KEY="php.install"

: "${MF_PHP_VERSION:=8.3}"

case "${MF_PHP_VERSION}" in
    7.4|8.0|8.1|8.2|8.3|8.4) ;;
    *)
        mini_forge_fail "$STEP_KEY" "invalid_php_version" "Unsupported PHP version [${MF_PHP_VERSION}]."
        ;;
esac

packages=(
    "php${MF_PHP_VERSION}-cli"
    "php${MF_PHP_VERSION}-fpm"
    "php${MF_PHP_VERSION}-mbstring"
    "php${MF_PHP_VERSION}-xml"
    "php${MF_PHP_VERSION}-curl"
    "php${MF_PHP_VERSION}-zip"
    "php${MF_PHP_VERSION}-mysql"
    "php${MF_PHP_VERSION}-sqlite3"
    "php${MF_PHP_VERSION}-bcmath"
    unzip
)

changed="false"
needs_install="false"

for package in "${packages[@]}"; do
    if [[ "$package" == unzip ]]; then
        if ! mini_forge_has_cmd unzip; then
            needs_install="true"
            break
        fi
        continue
    fi

    if ! dpkg -s "$package" >/dev/null 2>&1; then
        needs_install="true"
        break
    fi
done

if [[ "$needs_install" == "true" ]]; then
    mini_forge_apt_update
    mini_forge_apt_install "${packages[@]}"
    changed="true"
fi

php_bin="php${MF_PHP_VERSION}"
if ! mini_forge_has_cmd "$php_bin"; then
    php_bin="php"
fi

mini_forge_require_cmd "$php_bin"
php_version="$("$php_bin" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"

if [[ "$php_version" != "$MF_PHP_VERSION" ]]; then
    mini_forge_fail "$STEP_KEY" "php_version_mismatch" "Expected PHP ${MF_PHP_VERSION} but found ${php_version}."
fi

mini_forge_emit "$STEP_KEY" "true" "$changed" "{\"php_version\":\"${php_version}\"}"
