#!/bin/sh
# HotFii FreeRADIUS entrypoint.
#
# Renders the SQL module and clients.conf from templates, waits for PostgreSQL,
# then runs FreeRADIUS in the foreground logging to stdout so `docker compose
# logs freeradius` is the only place to look.
#
# Set RADIUS_DEBUG=1 to start in full debug mode (freeradius -X), which prints
# every request, every SQL statement and every reply attribute. Useful when a
# router is being rejected and you need to see why.

set -eu

RADDB=/etc/freeradius/3.0

: "${RADIUS_DB_HOST:=postgres}"
: "${RADIUS_DB_PORT:=5432}"
: "${RADIUS_DB_NAME:=hotfii}"
: "${RADIUS_DB_USER:=hotfii_radius}"
: "${RADIUS_LOCALHOST_SECRET:=hotfii-loopback}"

log() { printf '[hotfii:radius] %s\n' "$1" >&2; }

if [ -z "${RADIUS_DB_PASSWORD:-}" ]; then
    log "RADIUS_DB_PASSWORD is not set; refusing to start"
    exit 1
fi

export RADIUS_DB_HOST RADIUS_DB_PORT RADIUS_DB_NAME RADIUS_DB_USER \
    RADIUS_DB_PASSWORD RADIUS_LOCALHOST_SECRET

# An explicit variable list keeps envsubst away from FreeRADIUS's own ${dialect},
# ${modconfdir} and ${.:name} references.
log "rendering mods-available/sql"
envsubst '$RADIUS_DB_HOST $RADIUS_DB_PORT $RADIUS_DB_NAME $RADIUS_DB_USER $RADIUS_DB_PASSWORD' \
    < "$RADDB/mods-available/sql.template" > "$RADDB/mods-available/sql"

log "rendering clients.conf"
envsubst '$RADIUS_LOCALHOST_SECRET' \
    < "$RADDB/clients.conf.template" > "$RADDB/clients.conf"

# The password is now on disk inside the container; keep it away from everything
# but the daemon's own user.
chown freerad:freerad "$RADDB/mods-available/sql" "$RADDB/clients.conf"
chmod 640 "$RADDB/mods-available/sql" "$RADDB/clients.conf"

# read_clients reads the nas table during start-up, so PostgreSQL has to be
# reachable before the daemon starts, not merely soon after.
log "waiting for postgres at ${RADIUS_DB_HOST}:${RADIUS_DB_PORT}"
attempt=0
until nc -z "$RADIUS_DB_HOST" "$RADIUS_DB_PORT" 2>/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 60 ]; then
        log "postgres did not become reachable within 60 attempts"
        exit 1
    fi
    sleep 2
done

if [ "$#" -gt 0 ]; then
    log "executing: $*"
    exec "$@"
fi

if [ "${RADIUS_DEBUG:-0}" != "0" ]; then
    log "starting in debug mode"
    exec freeradius -X
fi

log "starting freeradius"
exec freeradius -f -l stdout
