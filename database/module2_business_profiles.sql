USE referral_platform;

CREATE TABLE IF NOT EXISTS business_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  business_name VARCHAR(150) NOT NULL,
  owner_name VARCHAR(100) NOT NULL,
  business_email VARCHAR(150) NOT NULL,
  business_phone VARCHAR(25) NOT NULL,
  business_category VARCHAR(100) NOT NULL,
  business_address VARCHAR(255) NOT NULL,
  city VARCHAR(100) NOT NULL,
  state VARCHAR(100) NOT NULL,
  country VARCHAR(100) NOT NULL,
  pincode VARCHAR(20) NOT NULL,
  business_description TEXT NOT NULL,
  website VARCHAR(255) NULL,
  gst_number VARCHAR(15) NULL,
  logo VARCHAR(255) NULL,
  verification_document VARCHAR(255) NULL,
  verification_status ENUM('Pending', 'Verified', 'Rejected') NOT NULL DEFAULT 'Pending',
  is_profile_completed TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY business_profiles_user_unique (user_id),
  KEY business_profiles_status_index (verification_status),
  CONSTRAINT business_profiles_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
