# Native Windows development setup

HotFii does not require Docker. The application can run natively on Windows, while FreeRADIUS runs in a small Linux VM, WSL environment, or remote laboratory host.

## 1. Required software

- PHP 8.4 x64 with curl, fileinfo, gd, intl, mbstring, openssl, pdo_pgsql, sodium, and zip.
- Composer 2.8 or newer.
- Node.js 22 LTS or newer.
- PostgreSQL 16 or newer.
- Redis 7 compatible server. A native Redis-compatible Windows service or WSL Redis is sufficient.
- Git.
- VirtualBox or Hyper-V only when running MikroTik CHR locally.

Confirm the active CLI is PHP 8.4:

~~~powershell
php -v
php -m
composer --version
node --version
npm --version
~~~

If XAMPP still owns the php command, place the PHP 8.4 directory before XAMPP in the user Path. The web server and CLI must use the same PHP version.

## 2. PostgreSQL

Create a database and a least-privilege application login:

~~~sql
CREATE ROLE hotfii LOGIN PASSWORD 'replace-with-a-long-password';
CREATE DATABASE hotfii OWNER hotfii ENCODING 'UTF8';
~~~

Copy .env.example to .env, then set the PostgreSQL password. Keep DB_CONNECTION=pgsql.

## 3. Application

~~~powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
~~~

For local Paystack testing use only test keys. A sandbox organization can test without platform payment approval.

## 4. Runtime processes

Use separate terminal windows during development:

~~~powershell
php artisan serve --host=127.0.0.1 --port=8000
php artisan queue:work redis --queue=critical,payments,network,default,notifications,reports --sleep=1 --tries=3 --timeout=120
php artisan reverb:start --host=0.0.0.0 --port=8080
php artisan schedule:work
npm run dev
~~~

The order of queues gives access expiry, disconnect, and payment work priority over reports.

For a persistent Windows pilot, register the web server, queue worker, Reverb, and scheduler with a service manager. Each process should restart on failure and write to separate log files. Do not run schedule:work and a once-per-minute Task Scheduler entry at the same time.

## 5. Local verification

~~~powershell
php artisan migrate:fresh --seed
php artisan test
php artisan route:list
php artisan schedule:list
php artisan hotfii:health
npm run build
~~~

Open /register for a new tenant or use the seeded demo accounts described in the root README.