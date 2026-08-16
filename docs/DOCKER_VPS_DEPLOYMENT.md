# Deploying HotFii to a VPS with Docker

Every command in this document runs **on the VPS** unless it says otherwise.
The result is HotFii serving `https://<your-domain>` with PostgreSQL 16, Redis 7,
a queue worker, the scheduler, Reverb WebSockets and FreeRADIUS 3.2 — all in one
Compose project.

The stack supplies PHP 8.4 itself, so nothing needs installing on the VPS beyond
Docker and Git.

---

## 0. What you need before you start

| Requirement | Notes |
|---|---|
| A VPS with Docker Engine + Compose v2 | Verify with `docker compose version` |
| 2 GB RAM minimum | 4 GB if you expect more than a few hundred concurrent sessions |
| A domain name | An **A record** pointing at the VPS public IP, already propagated |
| Ports open inbound | TCP 22, 80, 443 and UDP 1812, 1813 |
| Paystack keys | Live keys for real money; test keys are fine to start |

Check the A record has propagated before requesting a certificate — a failed
Let's Encrypt request counts against a per-hostname rate limit:

```bash
dig +short A your-domain.com
```

---

## 1. Firewall

Only five ports need to be reachable. PostgreSQL, Redis and Reverb are on the
private Compose network and are deliberately **not** published.

```bash
sudo ufw allow 22/tcp && sudo ufw allow 80/tcp && sudo ufw allow 443/tcp && sudo ufw allow 1812/udp && sudo ufw allow 1813/udp && sudo ufw enable
```

Confirm:

```bash
sudo ufw status verbose
```

> Docker publishes ports by writing its own iptables rules, which bypass `ufw`.
> The `ufw` rules above document intent and protect non-Docker ports; the
> authoritative restriction for 1812/1813 is the RADIUS shared secret, which is
> unique per router. If you want hard IP filtering for RADIUS, add rules to the
> `DOCKER-USER` chain rather than to `ufw`.

---

## 2. Clone the repository

```bash
cd /opt && sudo git clone https://github.com/<your-account>/hotfii.git && sudo chown -R "$USER":"$USER" /opt/hotfii && cd /opt/hotfii
```

---

## 3. Create the environment file

```bash
cp .env.docker.example .env && chmod 600 .env
```

Generate the four secrets. Run this and keep the output — you will paste it into
`.env` in a moment:

```bash
printf 'APP_KEY=base64:%s\nDB_PASSWORD=%s\nRADIUS_DB_PASSWORD=%s\nREVERB_APP_KEY=%s\nREVERB_APP_SECRET=%s\n' "$(openssl rand -base64 32)" "$(openssl rand -hex 24)" "$(openssl rand -hex 24)" "$(openssl rand -hex 16)" "$(openssl rand -hex 24)"
```

`APP_KEY` is generated here rather than with `php artisan key:generate` so the
database and the application key exist before the first container starts.
`openssl rand -base64 32` produces exactly what Laravel would.

Now edit the file:

```bash
nano .env
```

Everything that must change:

| Key | Value |
|---|---|
| `APP_DOMAIN` | `your-domain.com` — bare hostname, no `https://`, no trailing slash |
| `APP_URL` | `https://your-domain.com` |
| `LETSENCRYPT_EMAIL` | A real address; Let's Encrypt sends expiry warnings there |
| `APP_KEY` | From the command above, including the `base64:` prefix |
| `DB_PASSWORD` | From the command above |
| `RADIUS_DB_PASSWORD` | From the command above |
| `REVERB_APP_KEY`, `REVERB_APP_SECRET` | From the command above |
| `REVERB_HOST`, `REVERB_ALLOWED_ORIGINS` | `your-domain.com` |
| `MAIL_FROM_ADDRESS` | `hello@your-domain.com` |
| `PAYSTACK_PUBLIC_KEY`, `PAYSTACK_SECRET_KEY` | From your Paystack dashboard |
| `HOTFII_RADIUS_HOST` | The VPS **public IP**, not `localhost` and not `freeradius` |

`HOTFII_RADIUS_HOST` is the address routers are told to authenticate against, and
the address the queue worker sends CoA/Disconnect packets to. It has to be the
public IP.

`DB_PASSWORD` initialises PostgreSQL on first boot. Changing it later needs an
`ALTER ROLE` inside the running database, not just an edit here.

---

## 4. Get the TLS certificate

nginx will not start without a certificate file, and Certbot's HTTP challenge
needs a running nginx. This script breaks that deadlock — run it once:

```bash
bash infrastructure/certbot/init-letsencrypt.sh
```

It builds the images (a few minutes the first time), plants a temporary
self-signed certificate, starts nginx, obtains the real certificate over HTTP,
and reloads.

Rehearsing against Let's Encrypt's staging CA first is worth it if you are unsure
about DNS or the firewall — staging has a far more forgiving rate limit:

```bash
STAGING=1 bash infrastructure/certbot/init-letsencrypt.sh
```

After a successful staging run, delete the staging certificate and re-run without
`STAGING`:

```bash
docker compose run --rm certbot delete --cert-name your-domain.com
```

Renewal after this is automatic: the `certbot` service checks twice a day and the
`web` service reloads nginx on a 12-hour timer.

---

## 5. Start everything

```bash
docker compose up -d --build
```

The first build compiles PHP extensions and runs `npm run build`, so expect
5–15 minutes depending on the VPS. Subsequent builds reuse cached layers.

The `app` container runs the migrations on start-up (`migrate --force
--isolated`) and then applies the FreeRADIUS grants. The queue, scheduler,
Reverb and FreeRADIUS containers wait for it to become healthy first, so there is
no separate migrate step and no chance of two containers migrating at once.

Watch it come up:

```bash
docker compose ps
```

All services should read `running`, and `app`, `web`, `postgres` and `redis`
should read `healthy`. If `app` is restarting, read its log:

```bash
docker compose logs --tail=80 app
```

---

## 6. Verify

Run these in order. Each one tells you something the previous one could not.

**The app answers over TLS:**

```bash
curl -fsS -o /dev/null -w '%{http_code} %{ssl_verify_result}\n' https://your-domain.com/up
```

`200 0` means HTTP 200 and a certificate that verified.

**Every migration applied:**

```bash
docker compose exec -u www-data app php artisan migrate:status
```

**The platform's own health check:**

```bash
docker compose exec -u www-data app php artisan hotfii:health
```

**FreeRADIUS connected to PostgreSQL and loaded its clients:**

```bash
docker compose logs freeradius | grep -Ei 'rlm_sql|client|ready'
```

`Ready to process requests` is the line you want. `read_clients` loading zero
clients is expected until you register your first router.

**The queue worker is consuming:**

```bash
docker compose logs --tail=20 queue
```

**The RADIUS grants landed:**

```bash
docker compose exec postgres psql -U hotfii -d hotfii -c "\dp radacct" -c "\dp radcheck"
```

`hotfii_radius` should show `arw` on `radacct` and `r` on `radcheck` — read-write
on the accounting log, read-only on credentials, no delete anywhere.

---

## 7. Seed and create your first account

Register an organization through the web UI at `https://your-domain.com/register`.
That is the intended path and it exercises the real code.

The demo seeder exists for staging only. **It creates known passwords — never run
it on a production instance you expose:**

```bash
docker compose exec -u www-data app php artisan db:seed
```

To promote yourself to platform admin instead, do it in the database:

```bash
docker compose exec -u www-data app php artisan tinker --execute="App\Models\User::where('email','you@example.com')->update(['is_platform_admin' => true]);"
```

---

## 8. Point Paystack at the webhook

In the Paystack dashboard, set the webhook URL to:

```
https://your-domain.com/webhooks/paystack
```

The endpoint verifies the `x-paystack-signature` header, so it is safe to expose,
and it is exempt from CSRF by design (`bootstrap/app.php`).

---

## 9. Register your first router

Add a location and a network device in the dashboard. HotFii writes the router
into the `nas` table with its own generated shared secret and gives you a
RouterOS provisioning script.

**FreeRADIUS reads the `nas` table only at start-up.** After adding a router:

```bash
docker compose restart freeradius
```

Then smoke-test an issued credential from inside the container. Use a username
and password from a voucher you have activated or an internal identity you have
created:

```bash
docker compose exec freeradius radtest USERNAME PASSWORD 127.0.0.1 0 hotfii-loopback
```

A correct reply is `Access-Accept` carrying `Session-Timeout`,
`Mikrotik-Rate-Limit` and, for data-capped plans, `Mikrotik-Total-Limit`. If you
get `Access-Reject`, watch the daemon in debug mode while you retry:

```bash
docker compose stop freeradius && docker compose run --rm -e RADIUS_DEBUG=1 freeradius
```

That prints every SQL statement and every attribute decision.

---

## Day-to-day operations

**Deploy a new version:**

```bash
cd /opt/hotfii && git pull && docker compose up -d --build
```

Migrations run automatically on `app` start-up. Because `opcache.validate_timestamps`
is `0` and each container keeps its own compiled views, a rebuild is the only way
code changes take effect — which is the intent.

**Application logs** (containers log to stdout, so there are no log files to
rotate):

```bash
docker compose logs -f --tail=100 app queue scheduler
```

**Run any artisan command:**

```bash
docker compose exec -u www-data app php artisan <command>
```

The `-u www-data` matters. Running as root creates root-owned cache files that
the PHP-FPM workers then cannot overwrite.

**Restart one service:**

```bash
docker compose restart queue
```

**Back up the database:**

```bash
docker compose exec -T postgres pg_dump -U hotfii -Fc hotfii > "hotfii-$(date +%F).dump"
```

Keep these off the VPS. To restore into a fresh stack:

```bash
docker compose exec -T postgres pg_restore -U hotfii -d hotfii --clean --if-exists < hotfii-2026-08-16.dump
```

**Back up uploads and certificates:**

```bash
docker run --rm -v hotfii_app_public:/data -v "$PWD":/backup alpine tar czf /backup/hotfii-uploads.tar.gz -C /data .
```

**Stop everything** (volumes and data survive):

```bash
docker compose down
```

`docker compose down -v` also deletes the volumes — that destroys the database.

---

## Running the test suite

The production image excludes dev dependencies, so PHPUnit is not in it. Build a
second image that includes them. The tests use SQLite in memory, so no database
container is involved:

```bash
docker build --build-arg COMPOSER_FLAGS= --target app -t hotfii-app:test .
```

```bash
docker run --rm -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: hotfii-app:test php artisan test
```

---

## Troubleshooting

**`app` restarts in a loop, log says "No application encryption key"**
`APP_KEY` is empty or missing the `base64:` prefix in `.env`. Fix it, then
`docker compose up -d --force-recreate app queue scheduler reverb`.

**`web` will not start, "cannot load certificate"**
Step 4 has not run, or it ran for a different `APP_DOMAIN`. Check the domain
matches, then re-run `bash infrastructure/certbot/init-letsencrypt.sh`.

**Browser shows the page but styling is missing**
The `assets` build stage failed or `public/build` is stale. Rebuild without cache:
`docker compose build --no-cache web app && docker compose up -d`.

**Dashboard metrics never update live**
Reverb is reached through nginx on 443. Check `docker compose logs reverb`, and
confirm `REVERB_APP_KEY`, `REVERB_HOST` and `REVERB_SCHEME=https` are set. The
browser key is compiled into the JS bundle, so changing `REVERB_APP_KEY` requires
`docker compose up -d --build`, not a restart. A polling fallback covers the
dashboard meanwhile, so this degrades rather than breaks.

**Router gets `Access-Reject` for a credential that exists**
Almost always one of: the router was added after FreeRADIUS started
(`docker compose restart freeradius`), the shared secret in RouterOS does not
match the one HotFii generated, or the plan has expired. `RADIUS_DEBUG=1` shows
which.

**Accounting rows are not appearing in `radacct`**
The router is sending accounting to the wrong port, or UDP 1813 is blocked.
Check with `docker compose logs freeradius | grep -i accounting`.

**`permission denied` writing to `storage/`**
An artisan command was run as root. Fix ownership and use `-u www-data` next
time: `docker compose exec app chown -R www-data:www-data storage bootstrap/cache`.

**Behind Cloudflare or another proxy**
This stack assumes nginx is the edge: `REMOTE_ADDR` is the real client and
`fastcgi_param HTTPS on` makes Laravel emit `https://` URLs, with no
`X-Forwarded-*` header trusted. If you put another proxy in front, add
`$middleware->trustProxies(at: [...])` in `bootstrap/app.php` with that proxy's
addresses — do not use `at: '*'`, which would let any client forge its own IP in
the audit log.

---

## What this deployment does not do

Stated plainly so it is not discovered later:

- **Mail is `log` by default.** Password resets and email verification links are
  written to the container log, not delivered. Set the `MAIL_*` values to a real
  SMTP provider before onboarding operators.
- **No automatic off-site backup.** The `pg_dump` above is manual; wire it into
  cron and copy the dumps somewhere else.
- **FreeRADIUS needs a restart to see a new router.** `read_clients` is a
  start-up read.
- **One VPS, no redundancy.** Losing the host loses the service. The Compose
  volumes are the only copy of the data until you back them up.
- **Router certification still needs real hardware.** MikroTik support cannot be
  advertised as certified from a CHR lab alone.
