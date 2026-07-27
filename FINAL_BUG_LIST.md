# Final Bug List

Status date: 22 July 2026

## Resolved in this QA pass

| ID | Severity | Finding | Resolution |
| --- | --- | --- | --- |
| QA-001 | High | Login and account-registration requests had no CSRF token validation. | Added shared CSRF helpers and required tokens on both forms. Tokenless and invalid-token requests now fail safely. |
| QA-002 | High | A missing session token and a missing submitted token were treated as equal. | CSRF validation now explicitly rejects either empty value before comparison. |
| QA-003 | Medium | The NGO API returned data to unauthenticated callers. | The endpoint now returns a JSON `401 Authentication required` response unless the caller has a session. |
| QA-004 | Medium | The public document root exposed an email test page and lacked explicit production access controls for sensitive project paths. | Removed `test_email.php`; Apache configuration now disables indexes, protects environment/schema/dependency paths and private verification uploads, and sends baseline security headers. |
| QA-005 | Medium | Notification and email paths emitted verbose debug entries containing workflow and recipient details. | Removed the debug tracing; only concise operational failure messages remain. |
| QA-006 | Low | Report date filters wrapped `submitted_at` in `DATE()`, preventing effective use of the existing date index. | Replaced them with index-friendly inclusive/exclusive datetime range predicates. |
| QA-007 | Low | The unimplemented “Remember me” control appeared in the sign-in page. | Removed the dead control. |
| QA-008 | High | Existing databases had no migration that enforced the status enum used by the transition dropdown and controller. | Added `20260722_referral_status_workflow.sql` to align referral and history status enums with the five valid workflow values. |

## Verification blockers (not application defects)

| ID | Impact | Required action |
| --- | --- | --- |
| ENV-001 | Database-backed workflows, dashboard statistics, reports, exports, notification persistence, email triggers, and role-level journey tests could not run. | Start MySQL and provide a non-production QA database configured through `.env`. |
| ENV-002 | Firefox headless rendered the page but failed to map its framebuffer and did not create a screenshot. | Run the responsive/visual matrix on a workstation or CI browser runner with a functional graphics/browser environment. |
| ENV-003 | Live email delivery cannot be verified without a controlled SMTP inbox. | Configure a QA Mailtrap/Brevo/Gmail test mailbox and run the email checklist. |

No unresolved code defect was identified by the completed static and unauthenticated HTTP checks. Production approval remains conditional on clearing ENV-001 through ENV-003.
