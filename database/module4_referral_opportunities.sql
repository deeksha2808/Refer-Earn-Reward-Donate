USE referral_platform;

CREATE TABLE IF NOT EXISTS referral_opportunities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  category VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  service_location VARCHAR(150) NOT NULL,
  valid_until DATE NOT NULL,
  status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY referral_opportunities_business_index (business_id),
  KEY referral_opportunities_status_index (status),
  KEY referral_opportunities_category_index (category),
  KEY referral_opportunities_location_index (service_location),
  CONSTRAINT referral_opportunities_business_fk FOREIGN KEY (business_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opportunity_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  opportunity_id BIGINT UNSIGNED NOT NULL,
  product_name VARCHAR(150) NOT NULL,
  commission_percentage DECIMAL(5,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY opportunity_products_name_unique (opportunity_id, product_name),
  KEY opportunity_products_opportunity_index (opportunity_id),
  CONSTRAINT opportunity_products_opportunity_fk FOREIGN KEY (opportunity_id) REFERENCES referral_opportunities (id) ON DELETE CASCADE,
  CONSTRAINT opportunity_products_rate_check CHECK (commission_percentage > 0 AND commission_percentage <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
