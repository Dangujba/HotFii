# HotFii Android App: Joint Development Proposal

**Working document for:** Muhammad Baba Goni and Codex  
**Started:** 7 September 2026  
**Purpose:** Define how we will design, build, test, and release the HotFii Android application together.

## 1. Our Shared Goal

We will build an Android application that lets HotFii organization owners and staff run their daily hotspot business from a phone. The app will work with the existing HotFii Laravel platform and production database; it will not become a separate system with different sales, voucher, access, or billing rules.

Our first goal is a dependable Version 1.0 for real HotFii operators. It should make the most frequent tasks easy on a phone while keeping complicated and sensitive calculations on the server.

## 2. What We Are Building

The Android app will allow an authorized user to:

- Sign in and switch between permitted organizations.
- See a real dashboard with sales, voucher usage, fees, sessions, and router status.
- Create and review access plans.
- Choose midnight or rolling-hour validity for a plan.
- Generate voucher batches with PIN lengths of 2, 4, 6, 8, 10, or 12.
- View, share, download, and print vouchers.
- See voucher activations as pieces and printed-voucher sales as money.
- Review paginated paid transactions and HotFii charge-ledger entries.
- Record direct cash access when the organization's router is ready.
- Review customers and live sessions.
- Monitor routers and disconnect sessions when authorized.
- See accrued fees, collected fees, estimated billing, and invoices.
- View reports based on real database records.
- Receive useful payment, router, invoice, and account notifications.

The mobile app will use the same organization roles and permissions as the web application.

## 3. Product Rules We Will Preserve

The following decisions must behave identically on web and mobile:

- A paid voucher redemption is automatically a sale.
- Voucher activations are counted as pieces.
- Printed-voucher sales value is shown as cash sales without manual declaration.
- Direct cash activation is a separate transaction channel.
- Trial is an account stage, not free usage.
- Billable activity accrues the applicable HotFii fee from the first paid activation.
- Online fees collected through Paystack reduce what remains on the invoice.
- Complimentary vouchers and genuinely free plans are not billable.
- A midnight plan expires at the relevant calendar midnight in the organization's timezone.
- A rolling plan expires after its full number of hours from activation.
- The server is the final authority for prices, fees, expiry, permissions, and transaction status.

We will not duplicate these calculations inside the Android app. The app will request and display the server's result.

## 4. How We Will Work Together

### Muhammad's Role

Muhammad will:

- Explain how HotFii customers operate in real situations.
- Decide product behavior when more than one valid option exists.
- Review screens and wording from the operator's point of view.
- Provide test organizations, routers, Paystack test mode, and Android devices when needed.
- Test completed workflows and report what feels confusing or incorrect.
- Authorize production deployments and irreversible production operations.

### Codex's Role

Codex will:

- Study the existing code and database behavior before changing it.
- Explain important behavior and tradeoffs in plain language.
- Design and implement the mobile API and Android application.
- Keep business logic centralized in Laravel wherever practical.
- Add focused automated tests for important rules and regressions.
- Build and visually inspect screens at phone and tablet sizes.
- Check pagination, empty, loading, offline, error, and permission states.
- Keep changes in version control with clear commits.
- Verify builds and tests before proposing deployment.
- Deploy only after Muhammad gives explicit authorization.
- Maintain a running record of completed work and remaining decisions.

## 5. Our Development Rhythm

We will complete the application in small, reviewable parts. For each part, we will:

1. Confirm the exact user problem and expected behavior.
2. Inspect the current backend and production behavior.
3. Agree on any product decision that cannot be safely inferred.
4. Implement the API, Android screen, validation, and permissions together.
5. Add or update automated tests.
6. Build the application and inspect representative screen sizes.
7. Let Muhammad review the working feature.
8. Correct issues before moving to the next feature.
9. Commit the accepted work with a clear description.

This keeps the app usable throughout development and avoids leaving integration and testing until the end.

## 6. Proposed Technical Direction

### Android

Our starting recommendation is a native Android application using:

- Kotlin and Jetpack Compose.
- Material 3 components adapted to HotFii's identity.
- Retrofit and OkHttp for API requests.
- Kotlin coroutines.
- Room for appropriate local caching.
- WorkManager for safe background refresh.
- Firebase Cloud Messaging for push notifications.
- Android Keystore for protected authentication storage.

Native Kotlin is preferred because Android is the immediate target. We will revisit Flutter before implementation only if an iOS release becomes a near-term requirement.

### Backend

The Laravel application will remain the single backend. We will expand the existing `/api/v1` interface with:

- Mobile sign-in, token renewal, and logout.
- Organization context and role-aware permissions.
- Server-side filtering, sorting, and pagination.
- Idempotency for sales, voucher, and payment operations.
- Consistent validation and error responses.
- API documentation and automated feature tests.

The app will never connect directly to PostgreSQL, Redis, FreeRADIUS, routers, or private Paystack credentials.

## 7. Implementation Stages

### Stage 1: Foundation

- Confirm the app name, Android package name, supported versions, and identity.
- Create a separate development branch and Android project.
- Define the mobile design system and navigation.
- Add authentication and organization-context API support.
- Establish automated Android and API tests.

**Checkpoint:** Muhammad can install a development build, sign in, and enter the correct organization.

### Stage 2: Dashboard

- Build the mobile dashboard.
- Add real sales, fee, voucher, session, and router summaries.
- Use chart-library charts backed by API data.
- Add loading, refresh, empty, and error states.

**Checkpoint:** Dashboard figures match the web application for the same organization and period.

### Stage 3: Plans and Vouchers

- Build plan listing and creation.
- Support midnight and rolling validity.
- Build voucher batch creation and history.
- Support all approved PIN lengths.
- Add voucher document sharing, download, and printing.

**Checkpoint:** A plan and batch created on Android appear correctly on the web, and redemption produces the expected expiry.

### Stage 4: Sales and Customers

- Build sales summaries and paginated transaction history.
- Separate online, printed-voucher, and direct cash values clearly.
- Build voucher activation history.
- Add permitted direct cash activation.
- Build customer listing and customer details.

**Checkpoint:** A paid voucher redemption appears once, counts one piece, records its value, and accrues the correct fee.

### Stage 5: Network and Sessions

- Build router and controller status views.
- Display test state, heartbeat, location, and setup information.
- Build paginated live and recent session views.
- Add authorized session disconnect with confirmation.

**Checkpoint:** Status agrees with the server, and disconnect is never shown as successful unless the server confirms it.

### Stage 6: Finance and Reports

- Build the HotFii charge ledger and invoice views.
- Show accrued, collected, and estimated amounts clearly.
- Add Paystack invoice-payment hand-off.
- Build date and channel report filters with real charts.
- Add supported report export and sharing.

**Checkpoint:** Android, web, ledger, and invoice totals match exactly.

### Stage 7: Notifications and Settings

- Add push-notification registration and preferences.
- Add router, payment, invoice, and important account alerts.
- Build role-appropriate organization settings.
- Add secure device-session management and logout.

**Checkpoint:** Each role receives only permitted data and actions.

### Stage 8: Pilot and Release

- Run the complete automated test suite.
- Test small, medium, and large Android screens.
- Test slow, interrupted, and offline network conditions.
- Run a real pilot with selected HotFii organizations and routers.
- Fix pilot findings and prepare the signed Play Store build.
- Complete privacy, release, monitoring, and rollback documentation.

**Checkpoint:** Muhammad approves the release candidate after real operator testing.

## 8. Data and Offline Rules

The app may cache previously loaded information for viewing, but it must show when that information was last refreshed. Actions involving money, voucher creation, access activation, fees, or router changes require a confirmed response from the HotFii server.

The app will never display a failed or uncertain request as successful. Automatic retries will only be used when repeating a request cannot create a duplicate sale, voucher, payment, or access credential.

## 9. Security Rules

We will treat these as non-negotiable:

- HTTPS for all production communication.
- Secure Android token storage and server-side revocation.
- Organization isolation on every protected request.
- Server-side role enforcement even when a button is hidden on Android.
- No voucher PINs, passwords, tokens, or Paystack secrets in logs.
- No card details handled or stored by the app.
- Auditing for sensitive changes.
- No production credentials committed to Git.

## 10. Version 1.0 Definition of Done

Version 1.0 is complete only when:

- Core workflows work on supported Android devices.
- Important figures match the production web application.
- Pagination and filters work on every growing data list.
- Sales and fees cannot be duplicated by repeated taps or retries.
- Trial organizations use the same billing rules as the web platform.
- Midnight and rolling expiry pass timezone-aware tests.
- Each role sees only permitted organizations, records, and actions.
- Empty, loading, offline, validation, and server-error states are usable.
- Critical API and Android tests pass.
- A real-router pilot passes.
- Muhammad reviews and approves the release candidate.
- Backup, deployment, monitoring, and rollback steps are documented.

## 11. Later Versions

We will keep these items in the future backlog until Version 1.0 is stable:

- PPPoE subscriber management.
- New router integrations that have not passed hardware testing.
- Full platform-super-admin operation from mobile.
- iOS application.
- Advanced inventory and general accounting.
- Fully offline voucher generation or financial operations.

We can reorder this backlog together when real customer feedback shows which addition has the highest value.

## 12. Decisions Before Coding Starts

We will settle these points together at the beginning of Stage 1:

1. Final public app name and Android package name.
2. Minimum supported Android version.
3. Whether tablet layouts are required in Version 1.0.
4. Which roles may create vouchers and record direct cash access on mobile.
5. Which notifications should be enabled by default.
6. Which organizations and router types will join the pilot.
7. Whether iOS is expected soon enough to change the technology choice.

## 13. Our First Working Session

Our first implementation session will produce:

1. A Version 1.0 screen map and API endpoint map.
2. A runnable Android project using the agreed HotFii identity.
3. Secure sign-in connected to a test HotFii environment.

From that point onward, every stage will end with something Muhammad can run, inspect, and approve.
