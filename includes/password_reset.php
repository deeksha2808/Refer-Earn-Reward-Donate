<?php
declare(strict_types=1);

require_once __DIR__ . '/email_service.php';

const PASSWORD_RESET_TOKEN_TTL_MINUTES = 30;

function password_reset_request(string $email): bool
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $statement = db()->prepare('SELECT id, full_name, email, role FROM users WHERE email = ? LIMIT 1');
    $statement->execute([$email]);
    $user = $statement->fetch();
    if (!$user) {
        return false;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTimeImmutable('now'))->modify('+' . PASSWORD_RESET_TOKEN_TTL_MINUTES . ' minutes')->format('Y-m-d H:i:s');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $invalidate = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
        $invalidate->execute([(int) $user['id']]);
        $insert = $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
        $insert->execute([(int) $user['id'], $tokenHash, $expiresAt]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }

    try {
        $link = password_reset_link($token);
        (new EmailService())->send(
            (string) $user['email'],
            (string) $user['full_name'],
            'Reset your password | ' . APP_NAME,
            password_reset_email_html((string) $user['full_name'], $link),
            "Hello {$user['full_name']},\n\nReset your password using this secure link: {$link}\n\nThis link expires in " . PASSWORD_RESET_TOKEN_TTL_MINUTES . " minutes. If you did not request this, you can safely ignore this email."
        );
        ActivityLogService::logActivity((int) $user['id'], canonical_role((string) $user['role']), 'Authentication', 'Password Reset Requested', 'User', (int) $user['id'], 'Password reset email sent.');
        return true;
    } catch (Throwable $exception) {
        $delete = db()->prepare('DELETE FROM password_reset_tokens WHERE token_hash = ?');
        $delete->execute([$tokenHash]);
        throw $exception;
    }
}

function password_reset_token_is_valid(string $token): bool
{
    if (!preg_match('/\A[a-f0-9]{64}\z/i', $token)) return false;
    $statement = db()->prepare('SELECT id FROM password_reset_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
    $statement->execute([hash('sha256', $token)]);
    return (bool) $statement->fetchColumn();
}

function reset_password_with_token(string $token, string $password, string $confirmation): void
{
    if (!preg_match('/\A[a-f0-9]{64}\z/i', $token)) throw new RuntimeException('This password-reset link is invalid or has expired.');
    if ($password !== $confirmation) throw new RuntimeException('The new passwords do not match.');
    $strengthError = password_strength_error($password);
    if ($strengthError !== null) throw new RuntimeException($strengthError);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT prt.id, prt.user_id, u.role FROM password_reset_tokens prt JOIN users u ON u.id = prt.user_id WHERE prt.token_hash = ? AND prt.used_at IS NULL AND prt.expires_at > NOW() LIMIT 1 FOR UPDATE');
        $statement->execute([hash('sha256', $token)]);
        $reset = $statement->fetch();
        if (!$reset) throw new RuntimeException('This password-reset link is invalid, expired, or has already been used.');

        $update = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $update->execute([password_hash($password, PASSWORD_DEFAULT), (int) $reset['user_id']]);
        $delete = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
        $delete->execute([(int) $reset['user_id']]);
        $pdo->commit();
        ActivityLogService::logActivity((int) $reset['user_id'], canonical_role((string) $reset['role']), 'Authentication', 'Password Reset Completed', 'User', (int) $reset['user_id'], 'Password reset completed.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function password_strength_error(string $password): ?string
{
    if (strlen($password) < 10) return 'Use at least 10 characters.';
    if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) return 'Use uppercase, lowercase, and at least one number.';
    return null;
}

function password_reset_link(string $token): string
{
    return absolute_url('auth/reset_password.php?token=' . rawurlencode($token));
}

function password_reset_email_html(string $name, string $link): string
{
    $support = (string) (getenv('SMTP_FROM_EMAIL') ?: 'support@example.com');
    return '<!doctype html><html><body style="margin:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#1e2b43"><table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:28px 12px"><table width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#fff;border-radius:10px;overflow:hidden"><tr><td style="background:#1f4fbe;color:#fff;padding:24px;font-size:20px;font-weight:bold">' . e(APP_NAME) . '</td></tr><tr><td style="padding:30px"><h1 style="font-size:22px;margin-top:0">Reset your password</h1><p>Hello, <strong>' . e($name) . '</strong>.</p><p>We received a request to reset your password. Use the secure button below to choose a new password.</p><p style="margin:28px 0"><a href="' . e($link) . '" style="background:#2457d6;color:#fff;text-decoration:none;padding:12px 18px;border-radius:6px">Reset password</a></p><p>If the button does not work, copy and paste this link into your browser:</p><p style="word-break:break-all"><a href="' . e($link) . '" style="color:#2457d6">' . e($link) . '</a></p><p>This link expires in ' . PASSWORD_RESET_TOKEN_TTL_MINUTES . ' minutes. If you did not request a password reset, you can safely ignore this email; your password will not change.</p><p style="font-size:12px;color:#718096;margin-top:32px">Need help? Contact <a href="mailto:' . e($support) . '" style="color:#2457d6">' . e($support) . '</a>.</p></td></tr></table></td></tr></table></body></html>';
}
