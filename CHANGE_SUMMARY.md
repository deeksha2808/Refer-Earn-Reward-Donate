# Referral Status Dropdown Change Summary

## Files modified

- `business/referral_view.php`
- `CHANGE_SUMMARY.md`

## Changes made

- The Business Referral Review dropdown now always lists, in order: Under Review, Processing, Accepted, Rejected, and Completed.
- `Choose next status` remains the placeholder.
- The page accepts those four non-completion statuses directly, regardless of the referral's previous status.
- The existing completion modal, wallet-credit path, notifications, email delivery, activity logs, reports, and analytics behavior remain in use for Completed.

## Verification results

- Under Review, Processing, Accepted, and Rejected each saved successfully through the Business Referral Review endpoint and were confirmed in the database.
- Completed saved successfully through the existing completion flow; the referral reloaded as Completed and exactly one reward-credit wallet transaction was created.
- After reload, the dropdown contained all five required options in the required order.
- Temporary test records were removed after verification.
