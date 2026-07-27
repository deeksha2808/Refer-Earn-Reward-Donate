-- Refer • Earn • Reward & Donate — runtime/test-data reset
--
-- Run this while connected to the application's configured database.
-- This script preserves the database, schema, views, procedures, foreign keys,
-- indexes, configuration, source code, uploads, and master/lookup data.
-- No demo or sample data is inserted.

SET FOREIGN_KEY_CHECKS = 0;

-- Child/runtime tables: TRUNCATE removes all test data and resets their IDs.
TRUNCATE TABLE activity_logs;
TRUNCATE TABLE notifications;
TRUNCATE TABLE password_reset_tokens;
TRUNCATE TABLE referral_status_history;
TRUNCATE TABLE wallet_transactions;
TRUNCATE TABLE donations;
TRUNCATE TABLE opportunity_products;
TRUNCATE TABLE business_profiles;
TRUNCATE TABLE referrer_profiles;

-- Referenced runtime tables: DELETE avoids MySQL's TRUNCATE restriction on
-- tables which are targets of foreign keys, even when FK checks are disabled.
DELETE FROM customer_referrals;
DELETE FROM wallets;
DELETE FROM referral_opportunities;
DELETE FROM users;

-- Reset IDs for the DELETE-cleared runtime tables.
ALTER TABLE customer_referrals AUTO_INCREMENT = 1;
ALTER TABLE wallets AUTO_INCREMENT = 1;
ALTER TABLE referral_opportunities AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;

-- ngos is intentionally preserved as a potential master-data table. It was
-- empty at reset time; do not add sample data here.

SET FOREIGN_KEY_CHECKS = 1;
