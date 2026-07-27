# Notification Center

The Notification Center is the in-app counterpart to the existing email notifications. `NotificationService::notifyUser()` is the sole delivery entry point: it saves a typed notification for the recipient, then invokes the unchanged EmailService-backed email delivery.

## Database migration

Run `database/migrations/20260721_notification_center.sql` after the existing migrations. It upgrades the legacy `notifications` table with:

- `user_type`
- `message`
- `type`
- `reference_id`

Legacy `body` data is copied into `message`; it remains in older installations only for backward compatibility. New notification writes use the requested Notification Center fields.

## Types

`WELCOME`, `OPPORTUNITY`, `REFERRAL_SUBMITTED`, `REFERRAL_ACCEPTED`, `REFERRAL_REJECTED`, `REFERRAL_COMPLETED`, `WALLET_CREDIT`, and `SYSTEM`.

## User experience

Logged-in users have a notification bell in the header with an unread badge and a dropdown of the five latest notifications. The full `notifications.php` page orders newest first and supports pagination, read status, mark-one-read, mark-all-read, and delete actions. Blue dots indicate unread items; read items use grey styling.

## Delivery behavior

Notification persistence occurs before email delivery. SMTP errors are logged by the existing flow and do not remove the in-app notification or undo the originating business action.

## Testing guide

1. Run the Notification Center migration.
2. Trigger an existing welcome, opportunity, referral, or wallet event.
3. Confirm an email is still delivered through the configured provider.
4. Confirm the recipient sees the new in-app item, header badge, and dropdown entry.
5. Mark one notification read, mark all read, and delete one; confirm each action affects only the logged-in user's records.
6. Create more than 20 notifications and verify pagination and newest-first ordering.
