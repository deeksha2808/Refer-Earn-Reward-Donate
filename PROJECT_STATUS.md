# Project Status

_Generated from the repository contents on 19 July 2026. This report describes implemented code and schema only; it does not treat README plans as completed work._

## Project overview

Refer Earn Bill Reward and Donate is a PHP 8/MySQL web application for connecting businesses with referrers. Businesses publish referral opportunities and review customer introductions. Referrers maintain a profile, browse opportunities, submit referrals, receive wallet credits after a referral is marked `Completed`, and can donate their wallet balance.

The application is server-rendered PHP with Bootstrap loaded from CDNs, custom CSS/JavaScript, PDO MySQL access, filesystem uploads, and PHP sessions.

## Folder structure

```text
.
├── api/                 # NGO JSON endpoint
├── assets/              # CSS and browser JavaScript
├── auth/                # Registration, login, logout, NGO registration
├── business/            # Business profile, opportunities, referrals, analytics
├── config/              # Application/session/database configuration and web-server config
├── dashboard/           # Role-based dashboard redirect
├── database/            # Base schema, modules 2–7 SQL
├── includes/            # Shared helpers and domain functions
├── referrer/            # Referrer profile, referrals, wallet, donations, analytics
├── uploads/             # Uploaded business/referrer images and verification documents
├── access_denied.php
├── index.php            # Marketing/home page
├── README.md
└── .env.example
```

`admin/dashboard.php` is deleted in the current working tree; there is no remaining `admin/` implementation.

## Implemented features

- Public landing page, FAQ, and navigation.
- Account registration for `BUSINESS` and `REFERRER`, email uniqueness checks, password hashing, login/logout, session regeneration at login, role-aware redirects, flash messages, and role-restricted routes.
- Business five-step profile form with server-side validation, logo and verification-document uploads, completion state, and verification status display.
- Referrer six-step profile form with personal, professional, identity, bank/UPI, and profile-photo information; it supports server-side validation and JSON responses for its JavaScript submission flow.
- Business opportunity create, read, update, pause, close, delete, search, and filter views.
- Referrer opportunity search/filter/sort and opportunity-detail views for active, unexpired opportunities.
- Customer referral submission, business/referrer referral lists, detail pages, and business-side status updates.
- Wallet creation on first successful reward credit; balance, earnings, reward, donation totals; ledger/history pages; and duplicate reward-credit prevention at the database/application level.
- Wallet-funded donations with balance validation, donation records, and corresponding wallet-ledger entries.
- Business and referrer analytics pages and two reporting SQL views (`business_referral_summary`, `referrer_performance_summary`).
- Database tables for NGOs and notifications, an NGO registration form, and a JSON NGO endpoint.

## Pending or incomplete features

- No bill-upload feature exists. There are no bill-related routes, tables, upload handlers, or references in the codebase.
- No administrator role or administrator dashboard exists. The `users.role` enum accepts only `BUSINESS` and `REFERRER`.
- Profile verification is only a stored/displayed `Pending`/`Verified`/`Rejected` field. No approval workflow, reviewer UI, or code that changes verification status exists.
- Notifications have storage, an inbox, an unread-count badge, and a mark-all-read action. There is no email, SMS, or push delivery.
- There is no password reset despite the login modal advertising a future password-recovery feature. The “Remember me” checkbox is not processed.
- No payment gateway, withdrawal/payout flow, donation receipt, NGO portal, reporting export, activity log, automated tests, CI, or deployment automation is present.
- Analytics are summary cards/tables, not charts or downloadable reports.

## Database schema

| Table/view | Purpose |
| --- | --- |
| `users` | Accounts: name, email, phone, password hash, role, timestamps. |
| `business_profiles` | One profile per business user, business details, uploads, verification/completion state. |
| `referrer_profiles` | One profile per referrer user, identity, professional, bank/UPI, uploads, verification/completion state. |
| `referral_opportunities` | Business-owned opportunity, category, description, location, reward/project values, expiry, status. |
| `customer_referrals` | Referrer-to-business customer introduction, snapshot reward amount, customer data, review status. |
| `wallets` | One wallet per referrer and balance/aggregate totals. |
| `wallet_transactions` | Wallet ledger for reward credits, donations, and adjustments; reward credits are unique per referral/type. |
| `ngos` | NGO contact/location/website fields. |
| `donations` | Donation from a wallet, cause, amount, optional message/NGO, completed status. |
| `notifications` | Notification title/body/read state for a user. |
| `business_referral_summary` | Module 7 view with opportunity/referral/reward aggregates per business. |
| `referrer_performance_summary` | Module 7 view with referral, reward, and donation aggregates per referrer. |

The schema is split into a destructive base import (`database/referral_platform.sql`, which drops all tables) and module SQL files. Module 7 is a separate SQL file and is not included in the README’s listed import sequence.

## User roles and authorization

| Role | Implemented access |
| --- | --- |
| `BUSINESS` | Business profile, dashboard, opportunity management, referral review/status updates, business analytics. |
| `REFERRER` | Referrer profile, dashboard, opportunity browsing, referral submission/tracking, wallet, donations, referrer analytics. |

`require_login()` redirects unauthenticated visitors to login and can send role mismatches to `access_denied.php`. Not all business/referrer pages require a completed profile, although their dashboards and several related pages do.

## Authentication

Registration validates name/email/phone/password/role and uses `password_hash()`. Login fetches the account with a prepared statement, uses `password_verify()`, regenerates the session ID, and stores account data (without the password) in `$_SESSION`. Session cookies are `HttpOnly`, `SameSite=Lax`, and marked secure only when HTTPS is detected. Logout destroys the session and expires its cookie.

CSRF tokens are implemented and used on profile, opportunity, referral-status, donation, and NGO-registration POST forms. Login and account-registration POST forms do not include or verify CSRF tokens.

## Referral system

1. A completed business profile can create an opportunity with an open/closed/paused status and future expiry date.
2. Referrers can browse only `Open` opportunities whose `valid_until` date has not passed.
3. A referrer submits customer contact/details; the opportunity’s reward amount is copied into `customer_referrals` and the initial status is `Submitted`.
4. The owning business can move referrals forward through `Submitted` → `Under Review`/`Accepted` → `Completed`, or reject them before completion. `Rejected` and `Completed` are terminal.
5. Changing a referral to `Completed` calls the reward-credit function.

## Bill upload flow

Not implemented. Existing uploads are profile-related only:

- Business logo: PNG/JPEG up to 2 MB.
- Business verification document: PNG/JPEG/PDF up to 5 MB.
- Referrer profile photo: PNG/JPEG up to 2 MB.
- Referrer government-ID document: PNG/JPEG/PDF up to 5 MB.

Files are MIME-checked with `finfo`, assigned random names, and stored below `uploads/`.

## Reward system

When a business marks a referral `Completed`, `credit_referral_reward()` starts a database transaction, locks the referral, creates a wallet if needed, increases the wallet balance/earned/reward totals, and writes a `Reward Credit` transaction. The unique `(referral_id, transaction_type)` key prevents a second reward-credit ledger entry for the same referral.

The dashboard and wallet pages show current balance, lifetime earnings/rewards/donations, today’s credited rewards, pending referral reward totals, reward history, and transaction history.

## Donation system

A completed-profile referrer can donate only an amount greater than zero and no more than their available wallet balance. A transaction atomically decreases the wallet balance, increases `total_donated`, creates a completed donation record, and writes a `Donation` wallet transaction. The UI exposes fixed causes: Education, Child Welfare, Medical Help, Animal Rescue, Environmental Protection, and Old Age Home.

## NGO module

The database has an `ngos` table; `auth/ngo_register.php` allows any signed-in user to create an NGO; `includes/ngos.php` has registration/existence/list helpers; and `api/ngos.php` returns a JSON list intended for the donation form’s dynamically inserted NGO selector.

The donation form renders the optional NGO selector server-side and donation history displays the selected NGO. The JSON endpoint remains available for integrations.

## Notifications

Business referral status changes create a notification for the associated referrer. Signed-in users can view their notifications, see an unread badge, and mark them all read. There is no external delivery channel.

## Dashboard status

- Business dashboard: implemented metrics for opportunities and referral statuses/rewards, profile completion, verification display, and shortcuts.
- Referrer dashboard: implemented wallet totals, opportunity/referral queries, profile-verification display, and shortcuts.
- Analytics dashboards: implemented as metrics and recent-referral tables, dependent on Module 7 views being imported.
- `includes/dashboard.php` is an older generic dashboard template with placeholder `--` metrics and “future module” activity text; no current route uses it.

## Recent code changes

The latest commit is `5d56248` (“Improve README with project documentation”, 16 July 2026). The current working tree also contains uncommitted changes and additions, including:

- New business/referrer analytics pages and Module 7 SQL views.
- New NGO table/registration/API helper and optional donation NGO association.
- New notifications table/helper and notification creation during referral acceptance/completion.
- Referrer-profile AJAX validation/submission and client-side form enhancements.
- Opportunity category handling changes and removal of `required_referrals` from the opportunity schema.
- Removal of the former admin dashboard and removal of `PLATFORM_ADMIN` from the `users` role enum.

## Bugs and technical debt

- **Custom opportunity category cannot persist:** when “Other” is selected, validation replaces `category` with arbitrary text, but the database column is an enum whose only matching value is `Other`.
- **Client-side country/state conversion is broken:** the generic script searches for country selectors before it dynamically replaces the profile inputs, so it does not attach the state-population handler to the new controls. The replacement state selector has no options.
- **Hard-coded API path:** JavaScript fetches `/api/ngos.php`, which ignores `APP_BASE_URL` and will fail when the application is hosted under a subdirectory.
- **API authorization:** `api/ngos.php` has no login/role check.
- **Schema migration gap:** module files use `CREATE TABLE IF NOT EXISTS`; existing databases will not receive newly added NGO/donation/notification columns/tables without a dedicated migration. The base schema import is destructive.
- **Destructive opportunity deletion conflicts with referrals:** the schema uses `ON DELETE RESTRICT` for referrals, while the UI offers delete; deletion of an opportunity with referrals surfaces a generic save failure.
- **Security/test debt:** no tests, CI configuration, rate limiting, password-reset implementation, CSP/security headers, or server-side CSRF protection on login/registration are present. Uploaded files are stored inside the project’s public tree.

## Completion estimate

**Estimated 65% complete.** This is a code-based estimate, not a tracked project metric: core business/referrer profiles, opportunity/referral workflow, wallet crediting, and wallet donations are present; admin/verification workflows, functional NGO integration, user notifications, bill uploads, payouts, testing, and deployment/security hardening remain incomplete.

## Recommended next development tasks

1. Restrict NGO administration and decide on an appropriate delivery/fulfilment workflow for NGO-targeted donations.
2. Build the requested bill-upload model and workflow (schema, secure upload storage, review/approval, linkage to referrals/rewards).
3. Add an external notification delivery strategy (email, SMS, or push).
5. Add an admin role and protected verification/review screens for business/referrer profiles and NGO management.
6. Replace destructive bootstrap SQL with ordered, non-destructive migrations and include Module 7 installation explicitly.
7. Fix the profile country/state client-side behavior and write automated tests for authentication, authorization, referral transitions, reward idempotency, donations, and uploads.
8. Harden production security: CSRF on all state-changing requests, rate limiting, password reset, protected/non-public upload storage, security headers, and deployment/CI configuration.
