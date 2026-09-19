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

Implemented now: sign in, secure session restore, organization switching, and the real dashboard with router filtering. Later screens must consume server-calculated values and must not reimplement billing, fee, voucher, or expiry rules on the device.

## API Endpoint Map

| Method | Endpoint | Purpose | Stage |
|---|---|---|---|
| POST | `/api/v1/mobile/auth/login` | Issue a device-bound mobile token and return organization context | 1 |
| GET | `/api/v1/mobile/session` | Restore the authenticated user and organization list | 1 |
| DELETE | `/api/v1/mobile/auth/logout` | Revoke the current device token | 1 |
| GET | `/api/v1/mobile/organizations/{organization}/dashboard` | Real dashboard summaries and charts | 2 complete |
| GET/POST/PATCH/DELETE | `/api/v1/mobile/organizations/{organization}/plans` | Plan management | 3 |
| GET/POST/PATCH/DELETE | `/api/v1/mobile/organizations/{organization}/voucher-batches` | Voucher batch management | 3 |
| GET | `/api/v1/mobile/organizations/{organization}/sales` | Sales summaries and paginated transactions | 4 |
| POST | `/api/v1/mobile/organizations/{organization}/cash-activations` | Idempotent direct cash activation | 4 |
| GET | `/api/v1/mobile/organizations/{organization}/customers` | Customer list and details | 4 |
| GET | `/api/v1/mobile/organizations/{organization}/routers` | Router status and test results | 5 |
| GET | `/api/v1/mobile/organizations/{organization}/sessions` | Paginated live and recent sessions | 5 |
| POST | `/api/v1/mobile/organizations/{organization}/sessions/{session}/disconnect` | Authorized disconnect | 5 |
| GET | `/api/v1/mobile/organizations/{organization}/finance` | Fees, charge ledger, and invoices | 6 |
| GET | `/api/v1/mobile/organizations/{organization}/reports` | Filtered report datasets | 6 |

Every organization endpoint will use a public UUID, Sanctum authentication, token abilities, tenant membership, and server-side role checks.
