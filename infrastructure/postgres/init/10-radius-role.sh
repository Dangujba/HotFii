#!/bin/sh
# Runs once, on an empty PostgreSQL data directory, before anything else touches
# the database.
#
# This only creates the login FreeRADIUS uses. Table grants live in
# infrastructure/postgres/grant-radius.sql, which the app container applies after
# migrations — the rad* tables do not exist yet at this point.
#
# A .sh rather than a .sql file because the password has to come from the
# environment, and psql does not import environment variables into SQL.

set -eu

if [ -z "${RADIUS_DB_PASSWORD:-}" ]; then
    echo "10-radius-role: RADIUS_DB_PASSWORD is not set; refusing to create a passwordless login" >&2
    exit 1
fi

psql --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
    --no-psqlrc --quiet -v ON_ERROR_STOP=1 \
    -v pw="$RADIUS_DB_PASSWORD" <<'SQL'
-- NOLOGIN would be wrong: FreeRADIUS connects directly over TCP.
-- No CREATEDB, no CREATEROLE, no superuser, and no schema ownership. Every
-- table privilege is granted explicitly later, so this role starts with nothing
-- but the ability to connect.
CREATE ROLE hotfii_radius WITH LOGIN PASSWORD :'pw';

REVOKE ALL ON SCHEMA public FROM hotfii_radius;
GRANT USAGE ON SCHEMA public TO hotfii_radius;
SQL

echo "10-radius-role: created hotfii_radius"
