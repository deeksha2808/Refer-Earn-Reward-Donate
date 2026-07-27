<?php
declare(strict_types=1);
require_once __DIR__ . '/notification_service.php';
function create_notification(int $userId, string $title, string $message): int { return NotificationService::notifyUser($userId, $title, $message, $title, '<p>' . e($message) . '</p>'); }
function notifications_for_user(int $userId, int $limit = 20, int $offset = 0): array { $limit=max(1,min($limit,100));$offset=max(0,$offset);$stmt=db()->prepare("SELECT id,title,message,type,reference_id,is_read,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT {$limit} OFFSET {$offset}");$stmt->execute([$userId]);return $stmt->fetchAll(); }
function latest_notifications_for_user(int $userId, int $limit = 5): array { return notifications_for_user($userId, max(1, min($limit, 5))); }
function notification_count(int $userId): int { $stmt=db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=?');$stmt->execute([$userId]);return(int)$stmt->fetchColumn(); }
function unread_notification_count(int $userId): int { $stmt=db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');$stmt->execute([$userId]);return(int)$stmt->fetchColumn(); }
function mark_notifications_read(int $userId): void { $stmt=db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0');$stmt->execute([$userId]); }
function mark_notification_read(int $userId,int $notificationId): void {$stmt=db()->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?');$stmt->execute([$notificationId,$userId]);}
function delete_notification(int $userId,int $notificationId): void {$stmt=db()->prepare('DELETE FROM notifications WHERE id=? AND user_id=?');$stmt->execute([$notificationId,$userId]);}
