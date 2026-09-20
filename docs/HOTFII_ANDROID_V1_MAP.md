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

Implemented now: sign in, secure session restore, organization switching, the real dashboard with router filtering, full access-plan management, voucher batch management with A4 PDF and 58/88 mm Bluetooth printing, idempotent sales activity, direct cash activation, customer history, router readiness, live/recent session operations, finance and invoice views, Paystack invoice hand-off, and real-data reports with PDF/CSV sharing. Server-side rules remain authoritative for plan locking, prices, expiry, voucher state, sales classification, fees, invoice settlement, router state, disconnect confirmation, and permissions.

The primary bottom navigation is capped at five destinations: Home, Sales, Network, Finance, and More. Plans, Vouchers, Reports, and Account are available from the divided More list, and Android Back returns from a More child to that list.

## Current Delivery Status

- Stage 1 (Foundation): complete.
- Stage 2 (Dashboard): complete.
- Stage 3 (Plans and Vouchers): complete.
- Stage 4 (Sales and Customers): complete.
- Stage 5 (Network and Sessions): complete.
- Stage 6 (Finance and Reports): implemented and awaiting Muhammad's review/deployment checkpoint.
- Stage 7: complete. Theme selection, fingerprint protection, authenticator 2FA, secure logout, tenant notification history, push preferences, native Android channels, FCM installation registration, organization/portal settings, owner-only payment details, team roles, audit history, and personal device-session management are implemented.
- Stage 8: not started.

## API Endpoint Map

| Method | Endpoint | Purpose | Stage |
|---|---|---|---|
| POST | `/api/v1/mobile/auth/login` | Issue a device-bound mobile token and return organization context | 1 |
| GET | `/api/v1/mobile/session` | Restore the authenticated user and organization list | 1 |
| DELETE | `/api/v1/mobile/auth/logout` | Revoke the current device token | 1 |
| GET | `/api/v1/mobile/organizations/{organization}/dashboard` | Real dashboard summaries and charts | 2 complete |
| GET | `/api/v1/mobile/organizations/{organization}/notifications` | Paginated tenant notification history and preferences | 7 complete |
| PATCH | `/api/v1/mobile/organizations/{organization}/notifications/preferences` | Update router, payment, invoice, and account push preferences | 7 complete |
| POST | `/api/v1/mobile/organizations/{organization}/notifications/read` | Mark one or all tenant notifications read | 7 complete |
| PUT/DELETE | `/api/v1/mobile/device` | Register or remove the signed-in Android FCM device | 7 complete |
| GET/DELETE | `/api/v1/mobile/auth/sessions` | List personal Android sessions and revoke another device | 7 complete |
| GET/PATCH | `/api/v1/mobile/organizations/{organization}/settings` | Role-aware organization and portal settings with audit history | 7 complete |
| POST | `/api/v1/mobile/organizations/{organization}/settings/payment-profile` | Owner-only settlement and identity profile submission | 7 complete |
| GET/POST/PATCH | `/api/v1/mobile/organizations/{organization}/team` | Paginated team membership and role administration | 7 complete |
| GET/POST/PATCH/DELETE | `/api/v1/mobile/organizations/{organization}/plans` | Plan management | 3 complete |
| GET/POST/PATCH/DELETE | `/api/v1/mobile/organizations/{organization}/voucher-batches` | Voucher batch management | 3 complete |
| GET | `/api/v1/mobile/organizations/{organization}/sales` | Sales summaries, filters, transactions, and voucher activations | 4 complete |
| POST | `/api/v1/mobile/organizations/{organization}/sales/cash` | Record a direct cash activation and return one-time credentials | 4 complete |
| GET | `/api/v1/mobile/organizations/{organization}/customers` | Filtered and paginated customer list | 4 complete |
| GET | `/api/v1/mobile/organizations/{organization}/customers/{customer}` | Customer profile, recent sessions, and recent sales | 4 complete |
| GET | `/api/v1/mobile/organizations/{organization}/routers` | Filtered and paginated router status | 5 complete |
| GET | `/api/v1/mobile/organizations/{organization}/routers/{router}` | Router health, setup state, and readiness results | 5 complete |
| POST | `/api/v1/mobile/organizations/{organization}/routers/{router}/test` | Queue authorized readiness testing | 5 complete |
| GET | `/api/v1/mobile/organizations/{organization}/sessions` | Filtered and paginated live/recent sessions | 5 complete |
| POST | `/api/v1/mobile/organizations/{organization}/sessions/{session}/disconnect` | Confirmed, authorized session disconnect | 5 complete |
| GET | `/api/v1/mobile/organizations/{organization}/finance` | Current fee totals, independent ledger/invoice pagination, and filters | 6 complete |
| GET | `/api/v1/mobile/organizations/{organization}/finance/invoices/{invoice}` | Tenant-scoped invoice detail and payment permission | 6 complete |
| POST | `/api/v1/mobile/organizations/{organization}/finance/invoices/{invoice}/pay` | Start authenticated Paystack invoice payment | 6 complete |
| GET | `/api/v1/mobile/organizations/{organization}/reports` | Date- and router-filtered real chart datasets | 6 complete |
| GET | `/api/v1/mobile/organizations/{organization}/reports/export/csv` | Download the filtered transaction report as CSV | 6 complete |
| GET | `/api/v1/mobile/organizations/{organization}/reports/export/pdf` | Download the filtered web-equivalent report as PDF | 6 complete |

Every organization endpoint will use a public UUID, Sanctum authentication, token abilities, tenant membership, and server-side role checks.
