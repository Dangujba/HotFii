#!/usr/bin/env bash
# Obtain the first Let's Encrypt certificate for HotFii.
#
# Run this ONCE, after filling in .env and before `docker compose up -d`:
#
#   ./infrastructure/certbot/init-letsencrypt.sh
#
# Why it is needed: the nginx config hard-references
# /etc/letsencrypt/live/$APP_DOMAIN/fullchain.pem. nginx refuses to start when
# ssl_certificate points at a missing file, and certbot's webroot challenge needs
# a running nginx. This breaks the deadlock by planting a self-signed dummy
# certificate, starting nginx, swapping in the real certificate, and reloading.
#
# Renewals afterwards need no intervention: the certbot service runs
# `certbot renew` twice a day, and the web service reloads nginx on a 12h timer.
#
# Prerequisites: an A record for APP_DOMAIN pointing at this VPS, and inbound
# TCP 80 open. The CA fetches the challenge over plain HTTP.

set -euo pipefail

cd "$(dirname "$0")/../.."

if [ ! -f .env ]; then
    echo "error: .env not found. Run: cp .env.docker.example .env && nano .env" >&2
    exit 1
fi

read_env() {
    # Tolerates quoting and inline whitespace without sourcing the whole file.
    sed -n "s/^$1=//p" .env | head -1 | tr -d "\"'" | xargs
}

APP_DOMAIN="$(read_env APP_DOMAIN)"
LETSENCRYPT_EMAIL="$(read_env LETSENCRYPT_EMAIL)"

: "${APP_DOMAIN:?APP_DOMAIN is not set in .env}"
: "${LETSENCRYPT_EMAIL:?LETSENCRYPT_EMAIL is not set in .env}"

# Set STAGING=1 while you are still testing DNS or firewall rules. The
# production CA allows only a handful of failures per hostname per hour.
STAGING="${STAGING:-0}"

CERT_PATH="/etc/letsencrypt/live/${APP_DOMAIN}"
# Matches `name: hotfii` in docker-compose.yml plus the volume key.
CONF_VOLUME="hotfii_certbot_conf"

# The certbot service already declares the letsencrypt and webroot volumes, so
# `compose run` gives us a shell with both mounted — and creates them with the
# labels Compose expects.
in_certbot() { docker compose run --rm --no-deps --entrypoint /bin/sh certbot -c "$1"; }

echo "==> domain: ${APP_DOMAIN}"
echo "==> email:  ${LETSENCRYPT_EMAIL}"
echo "==> CA:     $([ "$STAGING" = "0" ] && echo production || echo staging)"

# A real certbot certificate always has a renewal config; a dummy never does.
if in_certbot "test -f /etc/letsencrypt/renewal/${APP_DOMAIN}.conf"; then
    echo "==> ${APP_DOMAIN} already has a certbot-managed certificate; nothing to do"
    echo "    to start over: docker compose run --rm certbot delete --cert-name ${APP_DOMAIN}"
    exit 0
fi

echo "==> planting a self-signed dummy certificate so nginx can start"
dummy_cert_cmd="mkdir -p ${CERT_PATH} && openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
    -keyout ${CERT_PATH}/privkey.pem -out ${CERT_PATH}/fullchain.pem -subj '/CN=localhost'"

if in_certbot "command -v openssl >/dev/null 2>&1"; then
    in_certbot "$dummy_cert_cmd"
else
    # The certbot image does not always carry the openssl CLI. The volume exists
    # with Compose's labels by now, so borrowing it from alpine is safe.
    docker run --rm -v "${CONF_VOLUME}:/etc/letsencrypt" alpine:3.20 \
        sh -c "apk add --no-cache openssl >/dev/null && ${dummy_cert_cmd}"
fi

echo "==> starting nginx with the dummy certificate"
docker compose up -d --build --no-deps web
sleep 5

echo "==> removing the dummy certificate"
in_certbot "rm -rf ${CERT_PATH} /etc/letsencrypt/archive/${APP_DOMAIN}"

staging_flag=""
if [ "$STAGING" != "0" ]; then
    staging_flag="--staging"
fi

echo "==> requesting the real certificate"
# shellcheck disable=SC2086
docker compose run --rm --no-deps certbot certonly \
    --webroot \
    --webroot-path=/var/www/certbot \
    --email "${LETSENCRYPT_EMAIL}" \
    --agree-tos \
    --no-eff-email \
    --non-interactive \
    ${staging_flag} \
    -d "${APP_DOMAIN}"

echo "==> reloading nginx"
docker compose exec web nginx -s reload

echo
echo "Done. ${APP_DOMAIN} has a live certificate."
echo "Next: docker compose up -d --build"
