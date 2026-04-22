#!/usr/bin/env sh
set -eu

cd /var/www/html

ROLE="${CONTAINER_ROLE:-app}"

wait_for_db() {
    if [ "${DB_CONNECTION:-}" != "mysql" ]; then
        return 0
    fi

    host="${DB_HOST:-mysql}"
    port="${DB_PORT:-3306}"
    user="${DB_USERNAME:-root}"
    pass="${DB_PASSWORD:-}"

    echo "Waiting for MySQL at ${host}:${port}..."
    until mysqladmin ping -h"${host}" -P"${port}" -u"${user}" -p"${pass}" --silent >/dev/null 2>&1; do
        sleep 2
    done
    echo "MySQL is ready."
}

prepare_app() {
    if [ ! -f ".env" ] && [ -f ".env.example" ]; then
        cp .env.example .env
    fi

    # Avoid stale local cache files (services/packages/config/routes/events)
    # that can reference dev-only providers absent in production image.
    rm -f bootstrap/cache/*.php || true

    mkdir -p storage \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache || true
}

set_env_value() {
    key="$1"
    value="$2"
    escaped_value=$(printf '%s' "$value" | sed 's/[\/&]/\\&/g')

    if grep -q "^${key}=" .env 2>/dev/null; then
        sed -i "s/^${key}=.*/${key}=${escaped_value}/" .env
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}

sync_runtime_env() {
    if [ -n "${DB_CONNECTION:-}" ]; then set_env_value "DB_CONNECTION" "${DB_CONNECTION}"; fi
    if [ -n "${DB_HOST:-}" ]; then set_env_value "DB_HOST" "${DB_HOST}"; fi
    if [ -n "${DB_PORT:-}" ]; then set_env_value "DB_PORT" "${DB_PORT}"; fi
    if [ -n "${DB_DATABASE:-}" ]; then set_env_value "DB_DATABASE" "${DB_DATABASE}"; fi
    if [ -n "${DB_USERNAME:-}" ]; then set_env_value "DB_USERNAME" "${DB_USERNAME}"; fi
    if [ -n "${DB_PASSWORD:-}" ]; then set_env_value "DB_PASSWORD" "${DB_PASSWORD}"; fi
    if [ -n "${SESSION_DRIVER:-}" ]; then set_env_value "SESSION_DRIVER" "${SESSION_DRIVER}"; fi
    if [ -n "${CACHE_STORE:-}" ]; then set_env_value "CACHE_STORE" "${CACHE_STORE}"; fi
    if [ -n "${QUEUE_CONNECTION:-}" ]; then set_env_value "QUEUE_CONNECTION" "${QUEUE_CONNECTION}"; fi
    if [ -n "${APP_URL:-}" ]; then set_env_value "APP_URL" "${APP_URL}"; fi
    if [ -n "${ZAP_HOST:-}" ]; then set_env_value "ZAP_HOST" "${ZAP_HOST}"; fi
    if [ -n "${ZAP_PORT:-}" ]; then set_env_value "ZAP_PORT" "${ZAP_PORT}"; fi
    if [ -n "${ZAP_API_KEY:-}" ]; then set_env_value "ZAP_API_KEY" "${ZAP_API_KEY}"; fi
}

wait_for_db
prepare_app
sync_runtime_env

case "${ROLE}" in
    app)
        # Best-effort template refresh for app container only.
        if command -v nuclei >/dev/null 2>&1; then
            (nuclei -update-templates >/dev/null 2>&1 || true) &
        fi
        php artisan config:clear || true
        php artisan package:discover --ansi || true
        php artisan migrate --force
        exec php artisan serve --host=0.0.0.0 --port=8000
        ;;
    worker)
        exec php artisan queue:work --timeout=3600 --tries=1
        ;;
    scheduler)
        while true; do
            php artisan schedule:run --no-interaction || true
            sleep 60
        done
        ;;
    *)
        echo "Unknown CONTAINER_ROLE: ${ROLE}"
        exit 1
        ;;
esac
