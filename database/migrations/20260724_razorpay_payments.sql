-- Migration: Razorpay Payment Integration
-- Date: 2026-07-24
-- Creates commission_payments and withdrawals tables

CREATE TABLE IF NOT EXISTS commission_payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  referral_id BIGINT UNSIGNED NOT NULL,
  business_id BIGINT UNSIGNED NOT NULL,
  referrer_id BIGINT UNSIGNED NOT NULL,
  razorpay_order_id VARCHAR(100) NULL,
  razorpay_payment_id VARCHAR(100) NULL,
  razorpay_signature VARCHAR(255) NULL,
  gross_commission DECIMAL(12,2) NOT NULL,
  platform_fee DECIMAL(12,2) NOT NULL,
  net_commission DECIMAL(12,2) NOT NULL,
  payment_method VARCHAR(50) NULL,
  status ENUM('created', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'created',
  paid_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY commission_payments_referral_unique (referral_id),
  UNIQUE KEY commission_payments_order_unique (razorpay_order_id),
  KEY commission_payments_business_index (business_id),
  KEY commission_payments_status_index (status),
  CONSTRAINT commission_payments_referral_fk FOREIGN KEY (referral_id) REFERENCES customer_referrals (id) ON DELETE RESTRICT,
  CONSTRAINT commission_payments_business_fk FOREIGN KEY (business_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT commission_payments_referrer_fk FOREIGN KEY (referrer_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS withdrawals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  referrer_id BIGINT UNSIGNED NOT NULL,
  wallet_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  razorpay_transfer_id VARCHAR(100) NULL,
  razorpay_payout_id VARCHAR(100) NULL,
  payment_method VARCHAR(50) NULL,
  status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
  reference_number VARCHAR(100) NULL,
  failure_reason VARCHAR(255) NULL,
  completed_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY withdrawals_referrer_index (referrer_id),
  KEY withdrawals_wallet_index (wallet_id),
  KEY withdrawals_status_index (status),
  CONSTRAINT withdrawals_referrer_fk FOREIGN KEY (referrer_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT withdrawals_wallet_fk FOREIGN KEY (wallet_id) REFERENCES wallets (id) ON DELETE RESTRICT,
  CONSTRAINT withdrawals_amount_positive CHECK (amount >= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Add 'Withdrawal' to wallet_transactions transaction_type enum
ALTER TABLE wallet_transactions MODIFY COLUMN transaction_type ENUM('Reward Credit','Donation','Adjustment','Withdrawal') NOT NULL;
