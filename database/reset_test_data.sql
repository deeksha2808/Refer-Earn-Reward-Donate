-- Refer Earn Bill Reward and Donate: clean QA data reset.
-- Preserves schema, views, migrations, configuration, and source code.
-- This intentionally clears all application records, including user accounts.

USE referral_platform;

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM notifications;
DELETE FROM wallet_transactions;
DELETE FROM wallets;
DELETE FROM referral_status_history;
DELETE FROM customer_referrals;
DELETE FROM opportunity_products;
DELETE FROM referral_opportunities;
DELETE FROM donations;
DELETE FROM ngos;
DELETE FROM business_profiles;
DELETE FROM referrer_profiles;
DELETE FROM users;

ALTER TABLE notifications AUTO_INCREMENT = 1;
ALTER TABLE wallet_transactions AUTO_INCREMENT = 1;
ALTER TABLE wallets AUTO_INCREMENT = 1;
ALTER TABLE referral_status_history AUTO_INCREMENT = 1;
ALTER TABLE customer_referrals AUTO_INCREMENT = 1;
ALTER TABLE opportunity_products AUTO_INCREMENT = 1;
ALTER TABLE referral_opportunities AUTO_INCREMENT = 1;
ALTER TABLE donations AUTO_INCREMENT = 1;
ALTER TABLE ngos AUTO_INCREMENT = 1;
ALTER TABLE business_profiles AUTO_INCREMENT = 1;
ALTER TABLE referrer_profiles AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;
