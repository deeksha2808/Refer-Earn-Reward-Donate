# Production Readiness Report

Status: **Conditional — not yet approved for deployment**  
Date: 22 July 2026

## Readiness improvements completed

- CSRF protection now covers every project POST endpoint.
- Session strict mode is enabled; login continues to regenerate the session ID.
- Anonymous access to the NGO JSON endpoint is rejected.
- Apache deployment configuration now blocks directory listings, `.env`, SQL dumps, Composer metadata, `database/`, `vendor/`, and private verification-document URLs.
- Baseline response headers are configured: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and `Permissions-Policy`.
- Temporary mail testing and verbose workflow debugging have been removed.
- Report date filtering is index-friendly, reducing avoidable database scanning.

## Deployment requirements

1. Serve the application over HTTPS and set `APP_URL` and `APP_BASE_URL` to the final public values.
2. Enable Apache `headers` module before using `config/refer-earn-reward-donate.conf`.
3. Store `.env` outside source control with unique production credentials; keep the document root free of database backups and developer-only files.
4. Apply every non-destructive migration in `database/migrations/` to a staging copy before production.
5. Configure a real SMTP provider and verify sender/domain alignment, delivery, and link targets.
6. Back up the production database, then run the complete QA matrix in `FINAL_QA_REPORT.md` on staging.

## Remaining release gates

- MySQL-backed business and referrer flows have not been executed in this environment.
- Dashboard totals, analytics, CSV/XLSX/PDF downloads, notification pagination, and emails need staging evidence.
- Browser responsive review needs successful screenshots from a working browser runner.

## Decision

The codebase is materially safer and cleaner after this pass, but release approval requires the remaining integration evidence. Do not deploy as production-ready until the release gates above pass on staging.
