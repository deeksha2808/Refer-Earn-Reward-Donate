# End-to-End QA Test Report

Date: 21 July 2026

## Passed workflow checks

- Business registration, login, and multipart profile completion passed with a new isolated business account.
- Referrer registration, login, and JSON profile completion passed with a new isolated referrer account.
- An active opportunity with a 12.5% product commission was created and persisted.
- Opportunity creation inserted an eligible-referrer in-app notification.
- A customer referral was submitted, then successfully transitioned through `Submitted → Under Review → Accepted → Completed`.
- Completion persisted final-sale details (`₹10,000`, `QA-INV-001`) and calculated a `₹1,250` commission.
- Exactly one `Reward Credit` ledger record for `₹1,250` was present for the completed referral.

## Not fully completed

- The rejection branch, reports/analytics/dashboard rendering assertions, notification-center event inventory, and activity-log inventory were not completed in this run.
- SMTP delivery was invoked by the workflows, but this environment does not expose a delivery receipt or mailbox; successful end-to-end delivery therefore remains unverified.
- The local test harness encountered session/server-lifetime issues between isolated server invocations. Those harness failures did not roll back the verified application data above.

## Result

**Partial pass — do not commit yet.** Resume QA from the rejection branch and complete the outstanding UI/data assertions.
