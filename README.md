# HotFii

HotFii is a multi-organization hotspot billing and network-access platform for Nigerian Starlink sellers, WISPs, markets, hotels, estates, offices, schools, and hybrid organizations.

This repository contains the web-first implementation: Laravel 13, PHP 8.4, Blade, Livewire 4, Bootstrap 5.3, AdminLTE 4, PostgreSQL, Redis queues, Reverb WebSockets, FreeRADIUS, MikroTik RouterOS provisioning, and Paystack split settlement. The frontend intentionally does not use Tailwind, React, Next.js, or jQuery.

Local development runs natively — on Windows, without containers. Production deploys as a Docker Compose stack on a Linux VPS, which is also what supplies PHP 8.4, PostgreSQL 16, Redis 7 and FreeRADIUS 3.2 in one place. See [docs/DOCKER_VPS_DEPLOYMENT.md](docs/DOCKER_VPS_DEPLOYMENT.md).

## What is implemented

- Immediate organization registration in commerce, internal, or hybrid mode.
- Tenant isolation, six organization roles, email verification, organization switching, and audited platform support impersonation.
- Multi-vendor adapter contract and guided Generic RADIUS support for standards-compliant routers.
- MikroTik RouterOS 7 provisioning, restricted monitoring user, optional WireGuard peer, CHR-ready testing, heartbeat health, RADIUS accounting, and CoA disconnect.
- Integration Test Center with configuration, heartbeat, authentication, accounting, captive portal, and disconnect evidence.
- Paid, free, and internal access plans with time, data, speed, and simultaneous-device limits.
- Captive portal with Paystack, hard-copy vouchers, browser QR scanning, staff login, and activation status.
- Secure voucher batches, encrypted codes, printable PDFs, activation-on-first-use, complimentary handling, and fee-ledger records.
- Paystack signature verification, durable webhook inbox, idempotent processing, test/live gating, payment-profile review, split transaction charges, and recovery jobs.
- Cash access activation, sales, finance, monthly invoice, Micro-to-Standard transition, grace, and suspension logic.
- Internal identities, temporary expiry, access groups, schedules, and synchronized FreeRADIUS policies.
- Redis queue lanes, scheduled recovery/reconciliation, private Reverb channels, Livewire metrics, and polling fallback.
- Operator reports and CSV exports, notifications, audit history, and a platform health dashboard.
- Sanctum and versioned integration endpoints under /api/v1.

Horizon is not forced into this Laravel 13 build because its currently published Composer constraint does not declare Laravel 13 compatibility. Native Redis workers and the platform queue-health dashboard are used until that package is compatible.

## Local development (native, no containers)

Prerequisites are PHP 8.4, Composer 2, Node.js 22+, PostgreSQL 16+, and Redis 7+. FreeRADIUS may run on a separate Linux VM or host while the Laravel application is developed on Windows.

~~~powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
~~~

Run the development processes in separate terminals:

~~~powershell
php artisan serve
php artisan queue:work redis --queue=critical,payments,network,default,notifications,reports --sleep=1 --tries=3 --timeout=120
php artisan reverb:start
php artisan schedule:work
npm run dev
~~~

Detailed native setup is in [docs/NATIVE_WINDOWS_SETUP.md](docs/NATIVE_WINDOWS_SETUP.md). FreeRADIUS and CHR instructions are in [docs/FREERADIUS_SETUP.md](docs/FREERADIUS_SETUP.md) and [docs/MIKROTIK_CHR_LAB.md](docs/MIKROTIK_CHR_LAB.md).

## Production deployment

On a VPS with Docker installed, three commands produce a running instance on HTTPS:

~~~bash
cp .env.docker.example .env   # then fill in domain, keys and passwords
bash infrastructure/certbot/init-letsencrypt.sh
docker compose up -d --build
~~~

The stack is PHP 8.4 FPM, Nginx with Certbot-managed TLS, PostgreSQL 16, Redis 7, a queue worker, the scheduler, Reverb, and FreeRADIUS 3.2 reading the same PostgreSQL database. Migrations and the least-privilege RADIUS grants apply automatically on start-up. The full walkthrough, verification steps and troubleshooting are in [docs/DOCKER_VPS_DEPLOYMENT.md](docs/DOCKER_VPS_DEPLOYMENT.md); the native Linux alternative is in [docs/PRODUCTION_RUNBOOK.md](docs/PRODUCTION_RUNBOOK.md).

## Demo account

After seeding:

- Operator: owner@hotfii.test
- Platform admin: admin@hotfii.test
- Password for both: HotFii-Test-2026!

Never run the demo seeder in production.

## Test commands

~~~powershell
php artisan test
npm run build
composer validate --strict
~~~

Router certification still requires a physical RouterOS 7 device before MikroTik can be advertised as certified. Other adapters remain Generic RADIUS compatible until their controller or hardware test suites are completed. Android begins after the real MikroTik web pilot.