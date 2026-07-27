<?php
declare(strict_types=1);

final class ActivityLogService
{
    public static function logActivity(int $userId, string $userType, string $module, string $action, string $entityType, ?int $entityId, string $description): void
    {
        try {
            db()->prepare('INSERT INTO activity_logs (user_id, user_type, action, module, entity_type, entity_id, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')->execute([
                $userId, substr($userType, 0, 30), substr($action, 0, 100), substr($module, 0, 50), substr($entityType, 0, 50), $entityId,
                substr($description, 0, 1000), self::ipAddress(), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'), 0, 1000),
            ]);
        } catch (Throwable $exception) {
            app_log('Activity logging failed: ' . $exception->getMessage());
        }
    }

    private static function ipAddress(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }
}
