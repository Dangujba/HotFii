# FreeRADIUS integration

FreeRADIUS is separate from Laravel so authentication continues even when an operator dashboard or WebSocket connection is unavailable. It reads credentials and reply attributes from the same PostgreSQL database and writes accounting records to radacct.

## Recommended topology

- Laravel, PostgreSQL, Redis, and Reverb may run on the development Windows host.
- FreeRADIUS runs on a small Linux VM or dedicated Linux server.
- UDP 1812 and 1813 are accepted only from registered NAS addresses.
- UDP 3799 is allowed from HotFii to managed NAS devices for CoA and disconnect.
- Production router management uses WireGuard. Do not expose RouterOS management publicly.

## Enable PostgreSQL SQL module

> Running the Docker stack? None of this is needed — `infrastructure/freeradius/`
> builds an image with all of it already applied. See
> [DOCKER_VPS_DEPLOYMENT.md](DOCKER_VPS_DEPLOYMENT.md). The instructions below are
> for a native FreeRADIUS install.

Install the FreeRADIUS PostgreSQL driver using the Linux host package manager — on Debian and Ubuntu that is `freeradius-postgresql`, which provides `rlm_sql_postgresql.so`.

Copy infrastructure/freeradius/sql-hotfii.example over `mods-available/sql`, replace the placeholders, and link it into mods-enabled:

~~~bash
sudo cp infrastructure/freeradius/sql-hotfii.example /etc/freeradius/3.0/mods-available/sql
sudo ln -sf ../mods-available/sql /etc/freeradius/3.0/mods-enabled/sql
~~~

The instance inside that file is named `sql`, and it has to stay that way. `sites-available/default` references the module as a bare `-sql`, so an instance named anything else is silently never called and every request falls through to reject.

Two edits to `sites-available/default` complete the wiring:

- `authorize`, `accounting` and `post-auth` already contain `-sql`. The leading dash makes the reference optional, so those sections need no change once the module is enabled.
- `session` contains a commented `#	sql`. **Uncomment it.** Without it the Simultaneous-Use attribute HotFii writes to radcheck is ignored, and one voucher works on unlimited devices at once.

Keep read_clients enabled so the nas rows created by HotFii are loaded. Clients are read only at start-up, so restart FreeRADIUS after adding a router, or configure dynamic client refresh for the deployed FreeRADIUS version.

Run foreground debug mode in the laboratory:

~~~bash
sudo freeradius -X
~~~

## Smoke test

After creating an internal identity or activating a voucher:

~~~bash
radtest USERNAME PASSWORD RADIUS_SERVER_IP 0 NAS_SECRET
~~~

A successful reply must include expected time, simultaneous-use, speed, and vendor data-limit attributes. Start a router session and confirm radacct receives start, interim, and stop updates.

The scheduled ReconcileRadiusAccounting job maps rows back to the tenant, router, customer, plan, and live session. It rejects accounting records where the NAS and credential do not belong to the same organization.

## Least privilege

The FreeRADIUS database login needs read access on nas and the radcheck, radreply, group, and user-group tables. It needs read and write access on radacct and radpostauth. It does not need access to payment profiles, voucher ciphertext, users, or Paystack data.

`infrastructure/postgres/grant-radius.sql` is the executable form of that paragraph, and the Docker stack applies it automatically after migrations. For a native install, run it as the database owner once the migrations have created the tables:

~~~bash
psql -U hotfii -d hotfii -f infrastructure/postgres/grant-radius.sql
~~~

## Schema note

The `nas` table carries `require_message_authenticator` and `limit_proxy_state` columns (added by `2026_08_16_000100_add_freeradius_client_columns_to_nas_table.php`). FreeRADIUS releases after the BlastRADIUS advisory select both in the stock PostgreSQL `client_query`. Without them `read_clients` fails at start-up and no router can authenticate. They are harmless on older releases, which simply do not select them.