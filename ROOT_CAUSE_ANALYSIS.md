# Opportunity and Referral Notification Root-Cause Analysis

## Incident

Opportunity creation and referral submission completed successfully, but no in-app notification or email was delivered.

## Root causes

### 1. Legacy `notifications.body` blocked every current notification insert

The installed database retained the original `notifications.body` column as `TEXT NOT NULL`, while the Notification Center implementation writes its replacement `message` column only. MySQL rejected every insert with the equivalent of: `Field 'body' doesn't have a default value`.

`NotificationService::notifyUser()` caught that exception and returned `0`, while the calling pages also caught notification exceptions. The originating opportunity/referral save therefore succeeded, but the notification failure was reduced to an application-log entry.

The fix makes legacy `body` nullable through an idempotent migration. Existing historical body data is retained; new notifications continue to use `message`.

### 2. Opportunity recipients were filtered with the wrong role casing

The installed `users.role` enum is `business`, `referrer`, `admin`. `NotificationService::opportunityCreated()` queried only `u.role = 'REFERRER'`, so it loaded zero eligible referrers even when their profile was complete.

The fix uses `LOWER(u.role) = 'referrer'`, which works with the deployed legacy schema and the documented uppercase schema.

## Execution-flow findings

| Workflow | Result |
| --- | --- |
| Business creates opportunity | Notification call is after `commit()` and before redirect. |
| Eligible referrers | Previously zero due to uppercase role predicate; now correctly found. |
| Referrer submits referral | Notification call is after `commit()` and uses the opportunity's `business_id`. |
| Business recipient lookup | Correct: `notifyUser()` resolves the supplied business ID from `users`. |
| Accepted / rejected | Calls `NotificationService::referralStatus()` after status update/history write. |
| Completed / wallet credit | Calls `NotificationService::referralCompletedAndWalletCredited()` after wallet/referral transaction commits. |
| Unread/header queries | Both filter by `notifications.user_id`; they do not incorrectly filter by `user_type`. |
| Email invocation | `NotificationService::sendEmail()` invokes `EmailService::send()` after a successful notification insert. |

## Transaction behavior

Notification inserts happen after the originating opportunity, referral, status, or wallet transaction commits. They are therefore not rolled back with those workflows. Email failure is now isolated after a successful in-app insert, so SMTP failure cannot hide or undo the notification row.
