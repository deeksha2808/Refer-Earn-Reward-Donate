USE referral_platform;

-- Allow the existing profile and opportunity category fields to retain a custom value selected through "Other".
ALTER TABLE business_profiles
  MODIFY business_category VARCHAR(100) NOT NULL;

ALTER TABLE referral_opportunities
  MODIFY category VARCHAR(100) NOT NULL;
