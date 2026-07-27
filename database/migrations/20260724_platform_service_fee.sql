-- Migration: Add platform service fee columns to customer_referrals
-- Date: 2026-07-24
-- Description: Stores gross commission, platform fee (2%), and net commission per referral

ALTER TABLE customer_referrals
  ADD COLUMN platform_fee DECIMAL(12,2) NULL AFTER calculated_commission,
  ADD COLUMN net_commission DECIMAL(12,2) NULL AFTER platform_fee;

-- Update the business_referral_summary view to include platform fee totals
CREATE OR REPLACE VIEW business_referral_summary AS
SELECT
  u.id AS business_id,
  (SELECT COUNT(*) FROM referral_opportunities ro WHERE ro.business_id = u.id) AS total_opportunities,
  (SELECT COUNT(*) FROM referral_opportunities ro WHERE ro.business_id = u.id AND ro.status = 'Active') AS active_opportunities,
  (SELECT COUNT(*) FROM referral_opportunities ro WHERE ro.business_id = u.id AND ro.status = 'Inactive') AS inactive_opportunities,
  (SELECT COUNT(*) FROM customer_referrals cr WHERE cr.business_id = u.id) AS total_referrals,
  (SELECT COUNT(*) FROM customer_referrals cr WHERE cr.business_id = u.id AND cr.status = 'Accepted') AS accepted_referrals,
  (SELECT COUNT(*) FROM customer_referrals cr WHERE cr.business_id = u.id AND cr.status = 'Completed') AS completed_referrals,
  (SELECT COALESCE(SUM(COALESCE(cr.calculated_commission, cr.reward_amount)), 0) FROM customer_referrals cr WHERE cr.business_id = u.id) AS total_referral_rewards,
  (SELECT COALESCE(SUM(cr.platform_fee), 0) FROM customer_referrals cr WHERE cr.business_id = u.id AND cr.status = 'Completed') AS total_platform_fees,
  (SELECT COALESCE(SUM(cr.net_commission), 0) FROM customer_referrals cr WHERE cr.business_id = u.id AND cr.status = 'Completed') AS total_net_commission
FROM users u
WHERE LOWER(u.role) = 'referrer' OR LOWER(u.role) = 'business';

-- Update the referrer_performance_summary view to include platform fee totals
CREATE OR REPLACE VIEW referrer_performance_summary AS
SELECT
  u.id AS referrer_id,
  (SELECT COUNT(*) FROM customer_referrals cr WHERE cr.referrer_id = u.id) AS total_referrals,
  (SELECT COUNT(*) FROM customer_referrals cr WHERE cr.referrer_id = u.id AND cr.status = 'Accepted') AS accepted_referrals,
  (SELECT COUNT(*) FROM customer_referrals cr WHERE cr.referrer_id = u.id AND cr.status = 'Completed') AS completed_referrals,
  (SELECT COALESCE(SUM(COALESCE(cr.calculated_commission, cr.reward_amount)), 0) FROM customer_referrals cr WHERE cr.referrer_id = u.id AND cr.status = 'Completed') AS total_rewards,
  (SELECT COALESCE(SUM(cr.platform_fee), 0) FROM customer_referrals cr WHERE cr.referrer_id = u.id AND cr.status = 'Completed') AS total_platform_fees,
  (SELECT COALESCE(SUM(cr.net_commission), 0) FROM customer_referrals cr WHERE cr.referrer_id = u.id AND cr.status = 'Completed') AS total_net_commission,
  (SELECT COALESCE(SUM(d.donation_amount), 0) FROM donations d JOIN wallets w ON w.id = d.wallet_id WHERE w.referrer_id = u.id) AS total_donations
FROM users u
WHERE LOWER(u.role) = 'referrer';
