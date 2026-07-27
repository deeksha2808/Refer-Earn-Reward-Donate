-- Rebuild analytics views with an explicit collation for role comparisons.
-- This prevents errors when a connection's default collation is
-- utf8mb4_0900_ai_ci while the users table uses utf8mb4_unicode_ci.

DROP VIEW IF EXISTS business_referral_summary;
CREATE VIEW business_referral_summary AS
SELECT
  u.id AS business_id,
  (SELECT COUNT(*) FROM referral_opportunities ro WHERE ro.business_id = u.id) AS total_opportunities,
  (SELECT COUNT(*) FROM referral_opportunities ro WHERE ro.business_id = u.id AND ro.status = 'Active') AS active_opportunities,
  (SELECT COUNT(*) FROM referral_opportunities ro WHERE ro.business_id = u.id AND ro.status = 'Inactive') AS inactive_opportunities,
  (SELECT COUNT(*) FROM customer_referrals cr WHERE cr.business_id = u.id) AS total_referrals,
  (SELECT COUNT(*) FROM customer_referrals cr WHERE cr.business_id = u.id AND cr.status = 'Accepted') AS accepted_referrals,
  (SELECT COUNT(*) FROM customer_referrals cr WHERE cr.business_id = u.id AND cr.status = 'Completed') AS completed_referrals,
  (SELECT COALESCE(SUM(COALESCE(cr.calculated_commission, cr.reward_amount)), 0) FROM customer_referrals cr WHERE cr.business_id = u.id) AS total_referral_rewards
FROM users u
WHERE u.role COLLATE utf8mb4_unicode_ci = _utf8mb4'business' COLLATE utf8mb4_unicode_ci;

DROP VIEW IF EXISTS referrer_performance_summary;
CREATE VIEW referrer_performance_summary AS
SELECT
  u.id AS referrer_id,
  (SELECT COUNT(*) FROM customer_referrals cr WHERE cr.referrer_id = u.id) AS total_referrals,
  (SELECT COUNT(*) FROM customer_referrals cr WHERE cr.referrer_id = u.id AND cr.status = 'Accepted') AS accepted_referrals,
  (SELECT COUNT(*) FROM customer_referrals cr WHERE cr.referrer_id = u.id AND cr.status = 'Completed') AS completed_referrals,
  (SELECT COALESCE(SUM(COALESCE(cr.calculated_commission, cr.reward_amount)), 0) FROM customer_referrals cr WHERE cr.referrer_id = u.id) AS total_rewards,
  (SELECT COALESCE(SUM(d.donation_amount), 0) FROM donations d JOIN wallets w ON w.id = d.wallet_id WHERE w.referrer_id = u.id) AS total_donations
FROM users u
WHERE u.role COLLATE utf8mb4_unicode_ci = _utf8mb4'referrer' COLLATE utf8mb4_unicode_ci;
