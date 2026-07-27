USE referral_platform;

-- Existing installations may still have the original fixed ENUM. Converting
-- it to VARCHAR retains every current value and allows custom categories.
ALTER TABLE business_profiles
  MODIFY COLUMN business_category VARCHAR(100) NOT NULL;
