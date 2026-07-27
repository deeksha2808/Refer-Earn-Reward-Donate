# Referral Workflow Update

## Status flow

The shared referral model and the live database already support these five statuses:

```text
Submitted → Under Review → Accepted → Completed
                         └→ Rejected
```

`Rejected` and `Completed` are terminal. The shared transition guard enforces this sequence; an invalid status cannot be submitted successfully.

## Files modified

| File | Change |
| --- | --- |
| `business/referral_view.php` | Displays all five statuses in the review dropdown, disables statuses that are not valid from the current state, retains the completion form for `Accepted → Completed`, adds a visible lifecycle note, and logs every status change including `Submitted → Under Review`. |

## Existing implementation verified

| Component | Location | Behavior |
| --- | --- | --- |
| Status enum/constants | `includes/customer_referrals.php`, `database/module5_customer_referrals.sql` | Defines Submitted, Under Review, Accepted, Rejected, and Completed. |
| Transition rules | `permitted_referral_statuses()` | Allows only Submitted → Under Review; Under Review → Accepted/Rejected; Accepted → Completed. |
| Status filters | `business/referrals.php` | Uses `CUSTOMER_REFERRAL_STATUSES`, so all five values are filterable. |
| Status badges | Business and referrer referral lists/views | Render the current stored status dynamically, including all five values. |

## Wallet trigger

`Accepted → Completed` uses the existing completion modal to collect the final sale amount. `complete_referral_with_commission()` then, in one transaction:

1. verifies the referral is Accepted;
2. calculates `sale amount × commission percentage`;
3. marks the referral Completed;
4. creates/updates the referrer wallet;
5. inserts a `Reward Credit` wallet transaction; and
6. records Completed status history.

## Notification and email triggers

- `Under Review → Accepted` and `Under Review → Rejected` call `NotificationService::referralStatus()`, which creates the referrer notification and invokes `EmailService`.
- `Accepted → Completed` calls `NotificationService::referralCompletedAndWalletCredited()` after the wallet transaction commits. It creates both Referral Completed and Wallet Credited notifications and invokes `EmailService` for each.
- All non-completion status changes now create an activity log, including Submitted → Under Review.
