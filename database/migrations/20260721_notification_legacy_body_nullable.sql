USE referral_platform;

-- Repairs databases that applied the original Notification Center migration
-- before body was made optional. New writes are stored in message.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notifications' AND column_name = 'body') > 0, 'ALTER TABLE notifications MODIFY body TEXT NULL', 'SELECT 1'); PREPARE notification_legacy_body_stmt FROM @sql; EXECUTE notification_legacy_body_stmt; DEALLOCATE PREPARE notification_legacy_body_stmt;
