-- Migration: Secure Referral Approval Workflow
-- Date: 2026-07-26
-- Adds referral_code, customer approval fields, and consent token

ALTER TABLE customer_referrals
  ADD COLUMN referral_code VARCHAR(30) NULL AFTER id,
  ADD COLUMN customer_approval_status ENUM('pending','waiting','approved','declined') NOT NULL DEFAULT 'pending' AFTER status,
  ADD COLUMN customer_approval_token VARCHAR(64) NULL,
  ADD COLUMN customer_approval_token_expires_at TIMESTAMP NULL,
  ADD COLUMN customer_approval_timestamp TIMESTAMP NULL,
  ADD UNIQUE KEY customer_referrals_code_unique (referral_code),
  ADD KEY customer_referrals_approval_token (customer_approval_token);

-- Update existing referral status enum to include new statuses
ALTER TABLE customer_referrals
  MODIFY COLUMN status ENUM('Submitted','Under Review','Processing','Waiting for Customer Approval','Customer Approved','Declined by Customer','Accepted','Rejected','Completed') NOT NULL DEFAULT 'Submitted';

-- Update referral_status_history to support new statuses
ALTER TABLE referral_status_history
  MODIFY COLUMN status ENUM('Submitted','Under Review','Processing','Waiting for Customer Approval','Customer Approved','Declined by Customer','Accepted','Rejected','Completed') NOT NULL;

-- Backfill referral_code for existing referrals
UPDATE customer_referrals SET referral_code = CONCAT('REF-', DATE_FORMAT(submitted_at, '%Y%m%d'), '-', LPAD(id, 6, '0')) WHERE referral_code IS NULL;

-- Make referral_code NOT NULL after backfill
ALTER TABLE customer_referrals MODIFY COLUMN referral_code VARCHAR(30) NOT NULL;
