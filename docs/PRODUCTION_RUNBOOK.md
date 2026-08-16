# Production runbook

Two supported production shapes:

- **Docker Compose on a single VPS** — the default. The whole stack, including PHP 8.4, PostgreSQL 16, Redis 7 and FreeRADIUS 3.2, comes from `docker-compose.yml`. Setup, verification, backup and troubleshooting are in [DOCKER_VPS_DEPLOYMENT.md](DOCKER_VPS_DEPLOYMENT.md), and it is the shorter path.
- **Native Linux services behind Nginx** — described below. Choose this when the host already runs these services, or when policy forbids containers.

## Native Linux deployment

Required supervised processes:

- Nginx and PHP-FPM 8.4.
- Redis and PostgreSQL.
- FreeRADIUS.
- Queue workers for critical, payments, network, default, notifications, and reports.
- Laravel Reverb.
- Laravel schedule:run once per minute.
- WireGuard.

Deployment sequence:

1. Enter maintenance mode only when a migration requires it.
2. Install Composer dependencies without development packages and with authoritative autoloading.
3. Build frontend assets with npm ci and npm run build.
4. Run migrations with force.
5. Optimize Laravel caches.
6. Gracefully restart PHP-FPM, queue workers, and Reverb.
7. Confirm /up, hotfii:health, Redis, queue depth, webhook backlog, RADIUS authentication, and a sandbox activation.
8. Exit maintenance mode.

Back up PostgreSQL and uploaded assets nightly. Encrypt backups, keep one copy outside the primary server, and perform a restore drill before the physical pilot. Rotate Paystack, Reverb, RADIUS, router API, and WireGuard secrets after suspected exposure.

## Docker deployment sequence

The equivalent sequence when running the Compose stack. Steps 2–5 above are handled by the image build and the `app` container's entrypoint, which migrates with `--isolated` so only one container can ever hold the migration lock.

1. `git pull`
2. `docker compose up -d --build` — builds, migrates, applies the RADIUS grants, warms caches, and recreates the PHP services.
3. Confirm `/up`, `hotfii:health`, queue log, `docker compose logs freeradius`, and a sandbox activation.

Maintenance mode, if a migration needs it:

~~~bash
docker compose exec -u www-data app php artisan down --render=errors::503
docker compose exec -u www-data app php artisan up
~~~

Adding a router requires `docker compose restart freeradius`, because `read_clients` reads the `nas` table only at start-up.