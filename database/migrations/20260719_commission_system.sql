USE referral_platform;

-- Safe incremental upgrade for existing installations (MySQL 8.0.29+).
CREATE TABLE IF NOT EXISTS ngos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(191) NOT NULL, email VARCHAR(191) NULL,
  phone VARCHAR(50) NULL, address VARCHAR(255) NULL, website VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY ngos_name_index (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, title VARCHAR(191) NOT NULL,
  body TEXT NOT NULL, is_read TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY notifications_user_index (user_id), CONSTRAINT notifications_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'referral_opportunities' AND column_name = 'required_referrals') > 0, 'ALTER TABLE referral_opportunities DROP COLUMN required_referrals', 'SELECT 1'); PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
-- Legacy installations retain the former fixed reward columns.  They are no
-- longer used by the product-based commission flow, so allow their neutral
-- default while preserving historic data.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'referral_opportunities' AND constraint_name = 'referral_opportunities_reward_positive' AND constraint_type = 'CHECK') > 0, 'ALTER TABLE referral_opportunities DROP CHECK referral_opportunities_reward_positive', 'SELECT 1'); PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'referral_opportunities' AND constraint_name = 'referral_opportunities_project_value_positive' AND constraint_type = 'CHECK') > 0, 'ALTER TABLE referral_opportunities DROP CHECK referral_opportunities_project_value_positive', 'SELECT 1'); PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
ALTER TABLE referral_opportunities
  MODIFY category VARCHAR(100) NOT NULL,
  MODIFY reward_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  MODIFY estimated_project_value DECIMAL(14,2) NOT NULL DEFAULT 0.00;
ALTER TABLE referral_opportunities MODIFY status VARCHAR(10) NOT NULL DEFAULT 'Active';
UPDATE referral_opportunities SET status = CASE WHEN status = 'Open' THEN 'Active' ELSE 'Inactive' END;
ALTER TABLE referral_opportunities MODIFY status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active';
CREATE TABLE IF NOT EXISTS opportunity_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, opportunity_id BIGINT UNSIGNED NOT NULL, product_name VARCHAR(150) NOT NULL,
  commission_percentage DECIMAL(5,2) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(id),
  UNIQUE KEY opportunity_products_name_unique(opportunity_id, product_name), KEY opportunity_products_opportunity_index(opportunity_id),
  CONSTRAINT opportunity_products_opportunity_fk FOREIGN KEY(opportunity_id) REFERENCES referral_opportunities(id) ON DELETE CASCADE,
  CONSTRAINT opportunity_products_rate_check CHECK (commission_percentage > 0 AND commission_percentage <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer_referrals' AND column_name = 'opportunity_product_id') = 0, 'ALTER TABLE customer_referrals ADD COLUMN opportunity_product_id BIGINT UNSIGNED NULL AFTER opportunity_id', 'SELECT 1'); PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer_referrals' AND column_name = 'product_name') = 0, 'ALTER TABLE customer_referrals ADD COLUMN product_name VARCHAR(150) NULL AFTER customer_notes', 'SELECT 1'); PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer_referrals' AND column_name = 'commission_percentage') = 0, 'ALTER TABLE customer_referrals ADD COLUMN commission_percentage DECIMAL(5,2) NULL AFTER product_name', 'SELECT 1'); PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer_referrals' AND column_name = 'sale_amount') = 0, 'ALTER TABLE customer_referrals ADD COLUMN sale_amount DECIMAL(14,2) NULL AFTER commission_percentage', 'SELECT 1'); PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer_referrals' AND column_name = 'calculated_commission') = 0, 'ALTER TABLE customer_referrals ADD COLUMN calculated_commission DECIMAL(12,2) NULL AFTER sale_amount', 'SELECT 1'); PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer_referrals' AND column_name = 'invoice_number') = 0, 'ALTER TABLE customer_referrals ADD COLUMN invoice_number VARCHAR(100) NULL AFTER calculated_commission', 'SELECT 1'); PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer_referrals' AND column_name = 'sale_date') = 0, 'ALTER TABLE customer_referrals ADD COLUMN sale_date DATE NULL AFTER invoice_number', 'SELECT 1'); PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer_referrals' AND column_name = 'completion_notes') = 0, 'ALTER TABLE customer_referrals ADD COLUMN completion_notes TEXT NULL AFTER sale_date', 'SELECT 1'); PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer_referrals' AND column_name = 'completed_at') = 0, 'ALTER TABLE customer_referrals ADD COLUMN completed_at TIMESTAMP NULL AFTER completion_notes', 'SELECT 1'); PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer_referrals' AND column_name = 'customer_state') = 0, 'ALTER TABLE customer_referrals ADD COLUMN customer_state VARCHAR(100) NULL AFTER customer_city', 'SELECT 1'); PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
CREATE TABLE IF NOT EXISTS referral_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, referral_id BIGINT UNSIGNED NOT NULL,
  status ENUM('Submitted', 'Under Review', 'Processing', 'Accepted', 'Rejected', 'Completed') NOT NULL,
  note VARCHAR(255) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY referral_status_history_referral_index (referral_id),
  CONSTRAINT referral_status_history_referral_fk FOREIGN KEY (referral_id) REFERENCES customer_referrals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'donations' AND column_name = 'ngo_id') = 0, 'ALTER TABLE donations ADD COLUMN ngo_id BIGINT UNSIGNED NULL AFTER message', 'SELECT 1'); PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
