USE referral_platform;

CREATE TABLE IF NOT EXISTS wallets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  referrer_id BIGINT UNSIGNED NOT NULL,
  current_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_earned DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_rewards DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_donated DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY wallets_referrer_unique (referrer_id),
  CONSTRAINT wallets_referrer_fk FOREIGN KEY (referrer_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT wallets_balance_nonnegative CHECK (current_balance >= 0),
  CONSTRAINT wallets_total_earned_nonnegative CHECK (total_earned >= 0),
  CONSTRAINT wallets_total_rewards_nonnegative CHECK (total_rewards >= 0),
  CONSTRAINT wallets_total_donated_nonnegative CHECK (total_donated >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  wallet_id BIGINT UNSIGNED NOT NULL,
  referral_id BIGINT UNSIGNED NULL,
  transaction_type ENUM('Reward Credit', 'Donation', 'Adjustment') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  balance_after DECIMAL(12,2) NOT NULL,
  description VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY wallet_transactions_reward_unique (referral_id, transaction_type),
  KEY wallet_transactions_wallet_index (wallet_id),
  KEY wallet_transactions_referral_index (referral_id),
  KEY wallet_transactions_type_index (transaction_type),
  CONSTRAINT wallet_transactions_wallet_fk FOREIGN KEY (wallet_id) REFERENCES wallets (id) ON DELETE CASCADE,
  CONSTRAINT wallet_transactions_referral_fk FOREIGN KEY (referral_id) REFERENCES customer_referrals (id) ON DELETE SET NULL,
  CONSTRAINT wallet_transactions_amount_positive CHECK (amount > 0),
  CONSTRAINT wallet_transactions_balance_nonnegative CHECK (balance_after >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ngos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(191) NOT NULL,
  email VARCHAR(191) NULL,
  phone VARCHAR(50) NULL,
  address VARCHAR(255) NULL,
  website VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ngos_name_index (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS donations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  wallet_id BIGINT UNSIGNED NOT NULL,
  cause_name VARCHAR(100) NOT NULL,
  donation_amount DECIMAL(12,2) NOT NULL,
  message TEXT NULL,
  ngo_id BIGINT UNSIGNED NULL,
  status ENUM('Completed') NOT NULL DEFAULT 'Completed',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY donations_wallet_index (wallet_id),
  KEY donations_status_index (status),
  KEY donations_ngo_index (ngo_id),
  CONSTRAINT donations_wallet_fk FOREIGN KEY (wallet_id) REFERENCES wallets (id) ON DELETE CASCADE,
  CONSTRAINT donations_ngo_fk FOREIGN KEY (ngo_id) REFERENCES ngos (id) ON DELETE SET NULL,
  CONSTRAINT donations_amount_positive CHECK (donation_amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  user_type VARCHAR(30) NOT NULL DEFAULT 'SYSTEM',
  title VARCHAR(191) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('WELCOME','OPPORTUNITY','REFERRAL_SUBMITTED','REFERRAL_ACCEPTED','REFERRAL_REJECTED','REFERRAL_COMPLETED','WALLET_CREDIT','SYSTEM') NOT NULL DEFAULT 'SYSTEM',
  reference_id BIGINT UNSIGNED NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY notifications_user_index (user_id),
  CONSTRAINT notifications_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
