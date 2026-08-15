# FreeRADIUS integration

FreeRADIUS is separate from Laravel so authentication continues even when an operator dashboard or WebSocket connection is unavailable. It reads credentials and reply attributes from the same PostgreSQL database and writes accounting records to radacct.

## Recommended topology

- Laravel, PostgreSQL, Redis, and Reverb may run on the development Windows host.
- FreeRADIUS runs on a small Linux VM or dedicated Linux server.
- UDP 1812 and 1813 are accepted only from registered NAS addresses.
- UDP 3799 is allowed from HotFii to managed NAS devices for CoA and disconnect.
- Production router management uses WireGuard. Do not expose RouterOS management publicly.

## Enable PostgreSQL SQL module

Install the FreeRADIUS PostgreSQL driver using the Linux host package manager. Copy infrastructure/freeradius/sql-hotfii.example to mods-available/sql-hotfii, replace placeholders, and link it into mods-enabled.

Enable sql in the authorize, accounting, post-auth, and session sections of the default and inner-tunnel virtual servers. Keep read_clients enabled so the nas rows created by HotFii are loaded. Restart FreeRADIUS after adding a router, or configure dynamic client refresh for the deployed FreeRADIUS version.

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