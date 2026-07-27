-- Notification Center upgrade. Safe for installations with the legacy body column.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notifications' AND column_name = 'user_type') = 0, 'ALTER TABLE notifications ADD COLUMN user_type VARCHAR(30) NOT NULL DEFAULT ''SYSTEM'' AFTER user_id', 'SELECT 1'); PREPARE notification_center_stmt FROM @sql; EXECUTE notification_center_stmt; DEALLOCATE PREPARE notification_center_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notifications' AND column_name = 'message') = 0, 'ALTER TABLE notifications ADD COLUMN message TEXT NULL AFTER title', 'SELECT 1'); PREPARE notification_center_stmt FROM @sql; EXECUTE notification_center_stmt; DEALLOCATE PREPARE notification_center_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notifications' AND column_name = 'type') = 0, 'ALTER TABLE notifications ADD COLUMN type ENUM(''WELCOME'', ''OPPORTUNITY'', ''REFERRAL_SUBMITTED'', ''REFERRAL_ACCEPTED'', ''REFERRAL_REJECTED'', ''REFERRAL_COMPLETED'', ''WALLET_CREDIT'', ''SYSTEM'') NOT NULL DEFAULT ''SYSTEM'' AFTER message', 'SELECT 1'); PREPARE notification_center_stmt FROM @sql; EXECUTE notification_center_stmt; DEALLOCATE PREPARE notification_center_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notifications' AND column_name = 'reference_id') = 0, 'ALTER TABLE notifications ADD COLUMN reference_id BIGINT UNSIGNED NULL AFTER type', 'SELECT 1'); PREPARE notification_center_stmt FROM @sql; EXECUTE notification_center_stmt; DEALLOCATE PREPARE notification_center_stmt;

-- Preserve every legacy notification while moving its visible text to message.
UPDATE notifications SET message = body WHERE message IS NULL;
ALTER TABLE notifications MODIFY message TEXT NOT NULL;

-- Legacy installations retain body for historical records. New notification
-- writes use message, so body must not remain a required duplicate column.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notifications' AND column_name = 'body') > 0, 'ALTER TABLE notifications MODIFY body TEXT NULL', 'SELECT 1'); PREPARE notification_center_stmt FROM @sql; EXECUTE notification_center_stmt; DEALLOCATE PREPARE notification_center_stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'notifications' AND index_name = 'notifications_user_type_created_index') = 0, 'CREATE INDEX notifications_user_type_created_index ON notifications (user_id, type, created_at, id)', 'SELECT 1'); PREPARE notification_center_stmt FROM @sql; EXECUTE notification_center_stmt; DEALLOCATE PREPARE notification_center_stmt;
