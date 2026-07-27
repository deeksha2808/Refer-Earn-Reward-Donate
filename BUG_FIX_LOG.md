# Bug Fix Log

## 21 July 2026

No application logic defect was confirmed during the completed portion of the QA pass.

- Referrer profile validation initially rejected `Intermediate`; this was a QA-input error. The permitted value is `1–3 Years`, after which the profile saved successfully.
- Several automation interruptions were caused by the local QA harness (shell quoting and the short-lived local PHP server), not by the application workflow.

## Follow-up defect to address

`business/referral_view.php` renders the Calculated Commission item without its closing wrapper before the completion-only fields. Browsers recover from the malformed markup, but it should be corrected before final visual QA.
