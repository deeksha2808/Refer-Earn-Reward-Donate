# Smart Notification & Email System

## Event flow

`NotificationService` in `includes/notification_service.php` is the single event-delivery service. Every event creates the in-app record first, then attempts email delivery. Email exceptions are caught and logged, so SMTP failures never undo a referral, campaign, or wallet workflow.

| Event | Recipient | In-app and email content |
| --- | --- | --- |
| New opportunity | All completed referrer profiles | Business, campaign, category, products/commission rates, direct opportunity link. |
| Referral submitted | Owning business | Referrer, customer, product, campaign, submission timestamp, review link. |
| Referral accepted/rejected | Referrer | Campaign, product, status, and referral link. |
| Referral completed | Referrer | Sale amount, commission rate/amount, wallet balance, and referral link. |
| Wallet credited | Referrer | Commission credit, sale/commission context, wallet balance, and wallet link. |

Status and wallet notifications are deliberately emitted after their database transaction commits, preventing a false completion email if a transaction rolls back.

## Inbox and schema

The existing `notifications` table stores `id`, `user_id`, `title`, `body`, `is_read`, and `created_at`. Module migration `database/migrations/20260721_notification_indexes.sql` adds:

- `(user_id, created_at, id)` for newest-first pagination
- `(user_id, is_read)` for unread badge counts and mark-all-read

`notifications.php` provides newest-first paging (20 per page), clear read/unread badges, mark-one-read, mark-all-read, and owner-scoped deletion. The global header reads the unread count for each page request; newly created rows are reflected on the next request.

## Email templates and SMTP

Emails use a reusable branded HTML template with a blue platform header, event heading, contextual fields, accessible plain-text fallback, and an action button.

PHPMailer is declared in `composer.json`. Install it on each deployment:

```bash
composer install --no-dev --optimize-autoloader
```

Copy `.env.example` to `.env` and configure only environment variables; no SMTP secret is in source code:

```dotenv
APP_URL=https://app.example.com
SMTP_ENABLED=true
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=...
SMTP_PASSWORD=...
SMTP_FROM_EMAIL=no-reply@example.com
SMTP_FROM_NAME=Refer Earn Bill Reward and Donate
```

With `SMTP_ENABLED=false` (the safe default), the in-app notification is still created and delivery is logged as skipped. Missing PHPMailer, invalid SMTP configuration, and SMTP failures are logged with `app_log()` and do not interrupt the primary workflow.

## Testing checklist

- [x] PHP syntax validation for notification service, inbox, and modified event pages.
- [x] Confirmed email configuration is environment-based and PHPMailer is declared as a Composer dependency.
- [x] Verified all required event call sites use `NotificationService`.
- [x] Rollback-only local service test verified submission, accepted, completed, and wallet-credit events create four in-app rows without leaving test data behind.
- [x] Rollback-only local test verified rejection plus new-opportunity fan-out matched the completed-referrer recipient count.
- [ ] Run `composer install` in the deployment environment.
- [ ] Configure a test SMTP account with `SMTP_ENABLED=true`.
- [ ] Create an opportunity and verify each completed referrer receives one in-app notification and email.
- [ ] Submit a referral and verify only its owning business receives the review notification/email.
- [ ] Accept, reject, and complete separate referrals; verify the corresponding referrer receives the stated events and correct financial values.
- [ ] Verify unread/read/delete/pagination behavior and badge count in a browser.
- [ ] Verify SMTP failures are logged while the campaign/referral/wallet action still succeeds.
