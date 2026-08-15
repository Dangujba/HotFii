# Production runbook

Run HotFii as native Linux services behind Nginx. Container tooling is not required.

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