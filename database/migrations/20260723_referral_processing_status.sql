USE referral_platform;

-- Add the Processing state required by the referral workflow without changing
-- existing referral data.
SET @expected_referral_status_type = "enum('Submitted','Under Review','Processing','Accepted','Rejected','Completed')";
SET @sql = IF(
  (SELECT column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'customer_referrals' AND column_name = 'status') <> @expected_referral_status_type,
  'ALTER TABLE customer_referrals MODIFY status ENUM(''Submitted'', ''Under Review'', ''Processing'', ''Accepted'', ''Rejected'', ''Completed'') NOT NULL DEFAULT ''Submitted''',
  'SELECT 1'
);
PREPARE referral_processing_status_stmt FROM @sql;
EXECUTE referral_processing_status_stmt;
DEALLOCATE PREPARE referral_processing_status_stmt;

SET @sql = IF(
  (SELECT column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'referral_status_history' AND column_name = 'status') <> @expected_referral_status_type,
  'ALTER TABLE referral_status_history MODIFY status ENUM(''Submitted'', ''Under Review'', ''Processing'', ''Accepted'', ''Rejected'', ''Completed'') NOT NULL',
  'SELECT 1'
);
PREPARE referral_processing_history_status_stmt FROM @sql;
EXECUTE referral_processing_history_status_stmt;
DEALLOCATE PREPARE referral_processing_history_status_stmt;
