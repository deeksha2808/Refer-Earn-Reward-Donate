# Cleanup Report

Date: 22 July 2026

## Removed

- `test_email.php`, a public SMTP test page that could be misused as an email relay.
- The unimplemented “Remember me” sign-in control.
- Verbose `[notification-debug]` logging from notification, opportunity, referral, and email workflows.

## Consolidated

- Moved CSRF token creation and verification into `includes/functions.php`, the shared bootstrap helper file.
- Removed the duplicate CSRF helper definitions from the business-profile module.

## Hardened

- Protected source/configuration and private document paths in the supplied Apache configuration.
- Enabled session strict mode and added baseline browser-security response headers.
- Changed report date filtering to use indexed datetime ranges.

## Intentionally retained

- Existing user-facing CSS, JavaScript, page layout, database schema, and business workflow behavior were left intact.
- Existing uncommitted work unrelated to this QA pass was preserved.

## Follow-up after staging QA

After the full browser/database test run, remove only assets proven unused by coverage or browser network inspection. No unverified CSS/JS removal was performed in this pass to avoid breaking established pages.
