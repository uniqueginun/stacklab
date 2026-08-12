STEP_KEY="deploy.build"

: "${MF_ROOT_PATH:?}"
: "${MF_COMMIT_SHA:?}"
: "${MF_SITE_TYPE:?}"

ROOT="${MF_ROOT_PATH}"
RELEASE="${ROOT}/releases/${MF_COMMIT_SHA}"
RUN_COMPOSER="${MF_RUN_COMPOSER:-1}"
RUN_NPM="${MF_RUN_NPM:-1}"
RUN_MIGRATIONS="${MF_RUN_MIGRATIONS:-1}"
RUN_CACHES="${MF_RUN_CACHES:-1}"

[[ -d "${RELEASE}" ]] || mini_forge_fail "$STEP_KEY" "missing_release" "Release directory does not exist."

cd "${RELEASE}"

if [[ "${RUN_COMPOSER}" == "1" && -f composer.json ]]; then
    mini_forge_require_cmd composer
    COMPOSER_NO_INTERACTION=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-ansi
fi

if [[ "${RUN_NPM}" == "1" ]]; then
    if [[ -f package-lock.json ]]; then
        if mini_forge_has_cmd npm; then
            npm ci --no-audit --no-fund
            if jq -e '.scripts.build // empty' package.json >/dev/null 2>&1 || grep -q '"build"' package.json 2>/dev/null; then
                npm run build
            fi
        fi
    elif [[ -f package.json ]] && mini_forge_has_cmd npm; then
        npm install --no-audit --no-fund
        if grep -q '"build"' package.json 2>/dev/null; then
            npm run build
        fi
    fi
fi

if [[ "${MF_SITE_TYPE}" == "laravel" && -f artisan ]]; then
    if [[ -s "${ROOT}/shared/.env" ]] && grep -qE '^APP_KEY=.+' "${ROOT}/shared/.env"; then
        if [[ "${RUN_MIGRATIONS}" == "1" ]]; then
            php artisan migrate --force --no-ansi || true
        fi
        if [[ "${RUN_CACHES}" == "1" ]]; then
            php artisan config:cache --no-ansi || true
            php artisan route:cache --no-ansi || true
            php artisan view:cache --no-ansi || true
        else
            rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/routes.php bootstrap/cache/events.php
        fi
    else
        rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/routes.php bootstrap/cache/events.php
    fi
fi

mini_forge_emit "$STEP_KEY" "true" "true" "{\"commit_sha\":\"${MF_COMMIT_SHA}\"}"
