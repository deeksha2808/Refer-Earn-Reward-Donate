CREATE DATABASE IF NOT EXISTS referral_platform
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE referral_platform;

-- Module 1 reset: remove every existing table, then recreate only users.
SET FOREIGN_KEY_CHECKS = 0;
SET @existing_tables = (
  SELECT GROUP_CONCAT(CONCAT('`', table_name, '`') SEPARATOR ', ')
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_type = 'BASE TABLE'
);
SET @drop_all_tables = IF(
  @existing_tables IS NULL,
  'SELECT 1',
  CONCAT('DROP TABLE IF EXISTS ', @existing_tables)
);
PREPARE reset_statement FROM @drop_all_tables;
EXECUTE reset_statement;
DEALLOCATE PREPARE reset_statement;

DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(25) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('BUSINESS', 'REFERRER') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY users_email_unique (email),
  KEY users_role_index (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
