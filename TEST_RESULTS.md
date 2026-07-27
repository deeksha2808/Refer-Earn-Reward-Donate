# Notification Fix Test Results

## Completed verification

| Check | Result | Evidence |
| --- | --- | --- |
| Installed role schema | Pass | `users.role` is `enum('business','referrer','admin')`; recipient query now accepts this. |
| Eligible referrer lookup | Pass | One completed `referrer` profile was found with the corrected predicate. |
| Opportunity linked to business | Pass | Opportunity `1` has `business_id = 1`, an existing business user. |
| Referral linked to business/referrer | Pass | Referral `1` has `business_id = 1` and `referrer_id = 2`, both existing users. |
| Legacy schema repair | Pass | `notifications.body` is nullable after migration. |
| Opportunity notification insert | Pass | Insert succeeded in a rolled-back database transaction. |
| Referral-submitted insert | Pass | Insert succeeded in a rolled-back database transaction. |
| Accepted insert | Pass | Insert succeeded in a rolled-back database transaction. |
| Rejected insert | Pass | Insert succeeded in a rolled-back database transaction. |
| Completed insert | Pass | Insert succeeded in a rolled-back database transaction. |
| Wallet-credit insert | Pass | Insert succeeded in a rolled-back database transaction. |
| PHP syntax | Pass | Modified PHP files passed `php -l`; JavaScript passed `node --check`. |

## Email delivery verification

`NotificationService` now reaches `EmailService` only after a confirmed notification insert, and both services log the handoff. Sending the actual verification messages was not performed because it would transmit real opportunity/referral content to the configured external Gmail recipients. Explicit approval is required before running that external delivery test.

## Manual production verification sequence

1. Create an opportunity as a business with at least one completed referrer profile.
2. Confirm `[notification-debug] Eligible referrers loaded` reports the expected IDs and that each recipient has an inserted `OPPORTUNITY` row.
3. Submit a referral and confirm a `REFERRAL_SUBMITTED` row for the opportunity's `business_id`.
4. Mark the referral accepted, rejected, and (on a separate accepted referral) completed; confirm the corresponding row types.
5. Confirm `[notification-debug] EmailService::send succeeded` for each recipient, or inspect its logged SMTP error if delivery is not configured.
