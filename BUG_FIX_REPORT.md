# Opportunity and Referral Notification Bug-Fix Report

## Fixed

- Made the legacy `notifications.body` column nullable through a safe, idempotent migration.
- Added a follow-up migration for installations that already applied the original Notification Center migration.
- Made opportunity recipient selection compatible with lowercase and uppercase role storage.
- Normalized stored notification/activity `user_type` values through `canonical_role()`.
- Separated email errors from the database insert path: an SMTP error is logged but cannot turn a successfully inserted notification into a reported failure.
- Added focused `[notification-debug]` tracing at opportunity creation, referral submission, every public notification method, recipient lookup, SQL insert success/failure, and email send entry/success/failure.

## Modified files

| File | Reason |
| --- | --- |
| `includes/notification_service.php` | Fix recipient filtering, normalize roles, preserve in-app notification on SMTP failure, and add diagnostic logs. |
| `includes/email_service.php` | Log entry and successful completion of each email send. |
| `business/opportunity_form.php` | Log the committed opportunity ID and notification dispatch handoff. |
| `referrer/referral_form.php` | Log the committed referral ID, sender, business recipient, and dispatch handoff. |
| `database/migrations/20260721_notification_center.sql` | Make legacy `body` optional for fresh Notification Center upgrades. |
| `database/migrations/20260721_notification_legacy_body_nullable.sql` | Repair already-upgraded databases safely. |

## Database action completed

Applied `database/migrations/20260721_notification_legacy_body_nullable.sql` to the local database. `notifications.body` now reports `IS_NULLABLE = YES`.

## Log prefix

Use the `[notification-debug]` prefix in the PHP error log to trace a workflow. It records the requested opportunity/referral IDs, sender, recipient, title/message, recipient email, service entry, recipient lookup, SQL result, and email handoff without logging SMTP credentials.
