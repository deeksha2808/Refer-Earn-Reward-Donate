# Final QA Report

Date: 22 July 2026  
Scope: final polish, security review, local HTTP regression checks, and static application audit.

## Completed checks

| Area | Result | Evidence |
| --- | --- | --- |
| PHP syntax | Pass | Every project PHP file outside `vendor/` passed `php -l`. |
| Public pages | Pass | `index.php`, sign-in, and registration returned HTTP 200 with no PHP warning/fatal/notice output. |
| Unauthenticated access | Pass | Business dashboard, referrer dashboard, reports, notifications, and activity logs returned HTTP 302 to sign-in. |
| API access | Pass | Unauthenticated `api/ngos.php` returns HTTP 401 JSON. |
| CSRF rendering | Pass | Login and registration forms render a 64-character CSRF token. |
| CSRF rejection | Pass | A tokenless login POST returned the safe form-expired message and did not reach authentication. |
| SQL-injection controls | Pass (static) | Inputs used in reviewed SQL are bound through PDO parameters; sort/type choices are allow-listed. |
| XSS controls | Pass (static) | Reviewed HTML output is escaped through `e()`; export cells are protected against spreadsheet formula injection. |
| Password handling | Pass (static) | Registration uses `password_hash`; login uses `password_verify`; login regenerates the session ID. |
| Role protection | Pass for unauthenticated access | Route guards redirect unsigned users. Authenticated cross-role checks await QA data. |
| Debug/console cleanup | Pass | No application `console.log`, `console.warn`, `console.error`, notification debug marker, or email test route remains. |
| Referral status transition matrix | Pass (unit-style) | Exhaustive checks confirmed only Submitted → Under Review, Under Review → Accepted/Rejected, and Accepted → Completed are permitted. |

## Required end-to-end matrix

The following scenarios are **blocked, not passed**, because MySQL was unavailable locally. Do not mark them complete until executed with isolated QA accounts and data.

| Flow | Status |
| --- | --- |
| Business registration, profile, sign-in, opportunity create/edit/view/delete | Blocked |
| Business referral status transitions: Submitted, Under Review, Accepted, Rejected, Completed | Blocked |
| Business dashboard metrics, analytics, activity logs, CSV/XLSX/PDF exports | Blocked |
| Referrer registration/profile/sign-in/opportunity browsing/referral submission | Blocked |
| Referrer wallet, earnings, donation, activity and profile update | Blocked |
| Notification unread count, read/all-read, pagination, ordering and icons | Blocked |
| Welcome/opportunity/referral/wallet email templates and delivery | Blocked |
| Dashboard statistic reconciliation after every state transition | Blocked |
| Responsive visual review at mobile/tablet/desktop widths | Blocked (headless renderer limitation) |

## QA execution prerequisites

1. Start MySQL and import/migrate a disposable QA database.
2. Configure `.env` with that database and a non-production SMTP inbox.
3. Use two isolated accounts: one Business and one Referrer.
4. Exercise every row in the blocked matrix, verifying database rows, dashboard totals, notifications, exports, and received email links.
5. Run browser checks at 360px, 768px, 1024px, and 1440px; capture screenshots for release evidence.

## Outcome

The completed checks pass. Full production QA is incomplete until the blocked matrix is executed in a functioning MySQL/browser/SMTP QA environment.
