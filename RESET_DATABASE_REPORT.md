# Database Reset Report

Date: 20 July 2026

## Result

The QA reset completed successfully using `database/reset_test_data.sql`. It preserved database tables, views, indexes, schema, migration files, configuration, and source code. Foreign-key checks were disabled only during the ordered deletes and re-enabled before completion.

## Cleared tables

| Table | Rows deleted | Post-reset rows | Next auto-increment |
| --- | ---: | ---: | ---: |
| `business_profiles` | 7 | 0 | 1 |
| `customer_referrals` | 4 | 0 | 1 |
| `donations` | 3 | 0 | 1 |
| `ngos` | 0 | 0 | 1 |
| `notifications` | 3 | 0 | 1 |
| `opportunity_products` | 7 | 0 | 1 |
| `referral_opportunities` | 6 | 0 | 1 |
| `referral_status_history` | 3 | 0 | 1 |
| `referrer_profiles` | 3 | 0 | 1 |
| `users` | 16 | 0 | 1 |
| `wallet_transactions` | 7 | 0 | 1 |
| `wallets` | 2 | 0 | 1 |
| **Total** | **61** | **0** | **All reset** |

## Preserved / skipped objects

- Preserved reporting views: `business_referral_summary` and `referrer_performance_summary`.
- No migration-history table exists in the current database, so none was altered.
- No report-cache, email-log, or audit-log table exists in the current database, so none was altered.
- Database schema, foreign keys, indexes, table definitions, migration SQL files, `.env` configuration, and application source code were not changed by the reset operation.

## Verification

- `referral_opportunities`: 0 rows
- `customer_referrals`: 0 rows
- `wallet_transactions`: 0 rows
- `notifications`: 0 rows
- All other base application tables: 0 rows
- Every cleared table reports `AUTO_INCREMENT = 1`.

The application is ready for a fresh end-to-end QA run. Create new business and referrer accounts before beginning role-based tests.
