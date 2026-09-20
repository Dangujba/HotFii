# HotFii Android Version 1 Map

Package: `com.innobytes.hotfii`  
Minimum Android: 8.0 / API 26  
Target Android: API 36

## Screen Map

1. Sign in
2. Organization workspace and switcher
3. Dashboard
4. Routers and controller status
5. Live and recent sessions
6. Plans
7. Voucher batches and printable vouchers
8. Sales and voucher activations
9. Customers
10. Finance, charge ledger, and invoices
11. Reports
12. Notifications and settings

Implemented now: sign in, secure session restore, organization switching, the real dashboard with router filtering, full access-plan management, voucher batch management with A4 PDF and 58/88 mm Bluetooth printing, sales activity, direct cash activation, and customer history. Server-side rules remain authoritative for plan locking, prices, expiry, voucher state, sales classification, fees, and permissions.

## Current Delivery Status

- Stage 1 (Foundation): complete.
- Stage 2 (Dashboard): complete.
- Stage 3 (Plans and Vouchers): complete.
- Stage 4 (Sales and Customers): implemented and awaiting Muhammad's review/deployment checkpoint.
- Stages 5-6: not started.
- Stage 7: theme selection, fingerprint protection, authenticator 2FA, and secure logout are implemented; notifications and the remaining settings are pending.
- Stage 8: not started.

## API Endpoint Map

| Method | Endpoint | Purpose | Stage |
|---|---|---|---|
| POST | `/api/v1/mobile/auth/login` | Issue a device-bound mobile token and return organization context | 1 |
| GET | `/api/v1/mobile/session` | Restore the authenticated user and organization list | 1 |
| DELETE | `/api/v1/mobile/auth/logout` | Revoke the current device token | 1 |
| GET | `/api/v1/mobile/organizations/{organization}/dashboard` | Real dashboard summaries and charts | 2 complete |
| GET/POST/PATCH/DELETE | `/api/v1/mobile/organizations/{organization}/plans` | Plan management | 3 complete |
| GET/POST/PATCH/DELETE | `/api/v1/mobile/organizations/{organization}/voucher-batches` | Voucher batch management | 3 complete |
| GET | `/api/v1/mobile/organizations/{organization}/sales` | Sales summaries, filters, transactions, and voucher activations | 4 complete |
| POST | `/api/v1/mobile/organizations/{organization}/sales/cash` | Record a direct cash activation and return one-time credentials | 4 complete |
| GET | `/api/v1/mobile/organizations/{organization}/customers` | Filtered and paginated customer list | 4 complete |
| GET | `/api/v1/mobile/organizations/{organization}/customers/{customer}` | Customer profile, recent sessions, and recent sales | 4 complete |
| GET | `/api/v1/mobile/organizations/{organization}/routers` | Router status and test results | 5 |
| GET | `/api/v1/mobile/organizations/{organization}/sessions` | Paginated live and recent sessions | 5 |
| POST | `/api/v1/mobile/organizations/{organization}/sessions/{session}/disconnect` | Authorized disconnect | 5 |
| GET | `/api/v1/mobile/organizations/{organization}/finance` | Fees, charge ledger, and invoices | 6 |
| GET | `/api/v1/mobile/organizations/{organization}/reports` | Filtered report datasets | 6 |

Every organization endpoint will use a public UUID, Sanctum authentication, token abilities, tenant membership, and server-side role checks.
