-- FreeRADIUS least-privilege grants.
--
-- Applied by the app container's entrypoint immediately after
-- `php artisan migrate`, because these tables do not exist when PostgreSQL runs
-- its init scripts. Re-running is harmless.
--
-- The shape mirrors the "Least privilege" section of docs/FREERADIUS_SETUP.md:
-- read the credential tables, write only the accounting and post-auth logs, and
-- never touch users, payments, vouchers or Paystack data.

\set ON_ERROR_STOP on

-- Read-only: HotFii owns every row here. FreeRADIUS decides nothing, it only
-- looks up what the app has already issued.
GRANT SELECT ON nas           TO hotfii_radius;
GRANT SELECT ON radcheck      TO hotfii_radius;
GRANT SELECT ON radreply      TO hotfii_radius;
GRANT SELECT ON radgroupcheck TO hotfii_radius;
GRANT SELECT ON radgroupreply TO hotfii_radius;
GRANT SELECT ON radusergroup  TO hotfii_radius;

-- Read-write: the accounting log. FreeRADIUS inserts Start, updates on Interim
-- and Stop, and reads it back for the Simultaneous-Use count query.
GRANT SELECT, INSERT, UPDATE ON radacct     TO hotfii_radius;
GRANT SELECT, INSERT         ON radpostauth TO hotfii_radius;

-- Both tables use bigserial primary keys, so INSERT also needs the sequence.
GRANT USAGE, SELECT ON SEQUENCE radacct_radacctid_seq TO hotfii_radius;
GRANT USAGE, SELECT ON SEQUENCE radpostauth_id_seq    TO hotfii_radius;

-- Deliberately absent, and worth keeping absent: no DELETE anywhere (an
-- accounting log FreeRADIUS can erase is not an audit trail), and no privilege
-- at all on users, organizations, customers, vouchers, voucher_codes,
-- transactions, payment_profiles, settlements or audit_logs.
--
-- Verify from the host with:
--   docker compose exec postgres psql -U hotfii -d hotfii \
--     -c "\dp" | grep hotfii_radius
