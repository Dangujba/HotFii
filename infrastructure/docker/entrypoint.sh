#!/bin/sh
# HotFii container entrypoint.
#
# Usage: hotfii-entrypoint <role>
#   app        PHP-FPM serving the dashboard, captive portal and API
#   queue      Redis queue worker across every HotFii lane
#   scheduler  php artisan schedule:work
#   reverb     Laravel Reverb WebSocket server
#   <other>    executed verbatim, e.g. `hotfii-entrypoint php artisan tinker`
#
# Only the app role migrates. Every role caches config so it starts warm.

set -eu

ROLE="${1:-app}"
APP_DIR=/var/www/html
cd "$APP_DIR"

log() { printf '[hotfii:%s] %s\n' "$ROLE" "$1" >&2; }

# ── Writable runtime directories ────────────────────────────────────────────
# storage/logs and storage/app/public arrive as volumes and may be owned by root
# the first time Compose creates them.
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# ── Wait for the datastores ─────────────────────────────────────────────────
DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_USERNAME="${DB_USERNAME:-hotfii}"
DB_DATABASE="${DB_DATABASE:-hotfii}"
REDIS_HOST="${REDIS_HOST:-redis}"
REDIS_PORT="${REDIS_PORT:-6379}"

log "waiting for postgres at ${DB_HOST}:${DB_PORT}"
attempt=0
until pg_isready -q -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE"; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 60 ]; then
        log "postgres did not become ready within 60 attempts"
        exit 1
    fi
    sleep 2
done

log "waiting for redis at ${REDIS_HOST}:${REDIS_PORT}"
attempt=0
until php -r 'exit(@fsockopen(getenv("REDIS_HOST") ?: "redis", (int) (getenv("REDIS_PORT") ?: 6379), $e, $s, 2) ? 0 : 1);'; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 60 ]; then
        log "redis did not become ready within 60 attempts"
        exit 1
    fi
    sleep 2
done

# ── Migrations and RADIUS grants, app role only ─────────────────────────────
if [ "$ROLE" = "app" ]; then
    # --isolated takes an atomic cache lock, so the queue, scheduler and Reverb
    # containers booting at the same moment can never migrate concurrently.
    log "running migrations"
    php artisan migrate --force --isolated

    # Grants must follow the migrations that create the RADIUS tables, so this
    # cannot live in the Postgres init directory.
    if [ -f /usr/local/share/hotfii/grant-radius.sql ]; then
        log "applying FreeRADIUS least-privilege grants"
        PGPASSWORD="${DB_PASSWORD:-}" psql \
            --quiet \
            --no-psqlrc \
            -v ON_ERROR_STOP=1 \
            -h "$DB_HOST" \
            -p "$DB_PORT" \
            -U "$DB_USERNAME" \
            -d "$DB_DATABASE" \
            -f /usr/local/share/hotfii/grant-radius.sql
    fi
fi

# ── Warm the framework caches ───────────────────────────────────────────────
# bootstrap/cache and storage/framework are per-container, so every role can do
# this independently without racing another container.
log "caching configuration, routes, views and events"
gosu www-data php artisan optimize

case "$ROLE" in
    app)
        log "starting php-fpm"
        # The FPM master stays root so it can bind and read the pool config; it
        # drops each worker to www-data itself.
        exec php-fpm
        ;;
    queue)
        log "starting queue worker"
        exec gosu www-data php artisan queue:work redis \
            --queue=critical,payments,network,default,notifications,reports \
            --sleep=1 \
            --tries=3 \
            --max-time=3600 \
            --timeout=120
        ;;
    scheduler)
        log "starting scheduler"
        exec gosu www-data php artisan schedule:work
        ;;
    reverb)
        log "starting reverb"
        exec gosu www-data php artisan reverb:start --host=0.0.0.0 --port=8080
        ;;
    *)
        log "executing: $*"
        exec gosu www-data "$@"
        ;;
esac
