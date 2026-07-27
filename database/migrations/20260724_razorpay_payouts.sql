-- Migration: Razorpay Payouts Integration
-- Date: 2026-07-24
-- Adds payout-related columns to withdrawals and contact/fund_account to referrer_profiles

-- Expand withdrawals status enum and add payout columns
ALTER TABLE withdrawals
  MODIFY COLUMN status ENUM('pending','processing','processed','completed','failed','reversed','cancelled') NOT NULL DEFAULT 'pending',
  ADD COLUMN razorpay_contact_id VARCHAR(100) NULL AFTER razorpay_payout_id,
  ADD COLUMN razorpay_fund_account_id VARCHAR(100) NULL AFTER razorpay_contact_id,
  ADD COLUMN utr_number VARCHAR(100) NULL AFTER razorpay_fund_account_id,
  ADD COLUMN payout_mode VARCHAR(20) NULL AFTER utr_number,
  ADD COLUMN processed_at TIMESTAMP NULL AFTER completed_at;

-- Add Razorpay contact/fund account IDs to referrer profiles for reuse
ALTER TABLE referrer_profiles
  ADD COLUMN razorpay_contact_id VARCHAR(100) NULL,
  ADD COLUMN razorpay_fund_account_id_bank VARCHAR(100) NULL,
  ADD COLUMN razorpay_fund_account_id_vpa VARCHAR(100) NULL;

-- Webhook event log to prevent duplicate processing
CREATE TABLE IF NOT EXISTS razorpay_webhook_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id VARCHAR(100) NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  entity_id VARCHAR(100) NULL,
  payload JSON NULL,
  processed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY webhook_event_unique (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
