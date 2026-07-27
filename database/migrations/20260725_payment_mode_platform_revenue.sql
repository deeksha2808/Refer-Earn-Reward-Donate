-- Migration: Platform Revenue table and payment mode support
-- Date: 2026-07-25

CREATE TABLE IF NOT EXISTS platform_revenue (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id BIGINT UNSIGNED NOT NULL,
  business_id BIGINT UNSIGNED NOT NULL,
  referral_id BIGINT UNSIGNED NOT NULL,
  platform_fee DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY platform_revenue_payment_index (payment_id),
  KEY platform_revenue_business_index (business_id),
  KEY platform_revenue_referral_index (referral_id),
  CONSTRAINT platform_revenue_payment_fk FOREIGN KEY (payment_id) REFERENCES commission_payments (id) ON DELETE RESTRICT,
  CONSTRAINT platform_revenue_business_fk FOREIGN KEY (business_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add transaction_reference to commission_payments
ALTER TABLE commission_payments ADD COLUMN transaction_reference VARCHAR(100) NULL AFTER payment_method;
