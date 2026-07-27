<?php
declare(strict_types=1);

require_once __DIR__ . '/email_service.php';

final class NotificationService
{
    public static function businessWelcome(int $businessId, string $businessName, string $accountEmail): void
    {
        self::notifyUser(
            $businessId,
            'Welcome to ' . APP_NAME,
            'Your business account has been registered successfully.',
            'Your business account is ready',
            '<p>Welcome, <strong>' . e($businessName) . '</strong>.</p><p>Your business account (<strong>' . e($accountEmail) . '</strong>) was registered successfully. Complete your business profile, then create your first referral opportunity to connect with eligible referrers.</p>',
            self::absoluteUrl('business/dashboard.php'), 'WELCOME'
        );
    }

    public static function referrerWelcome(int $referrerId, string $referrerName): void
    {
        self::notifyUser(
            $referrerId,
            'Welcome to ' . APP_NAME,
            'Your referrer account has been registered successfully.',
            'Welcome to the platform',
            '<p>Welcome, <strong>' . e($referrerName) . '</strong>.</p><p>Explore referral opportunities, submit genuine customer introductions, and track your rewards and wallet activity from your dashboard.</p>',
            self::absoluteUrl('referrer/dashboard.php'), 'WELCOME'
        );
    }

    public static function notifyUser(int $userId, string $title, string $body, string $emailHeading, string $emailBody, string $link = '', string $type = 'SYSTEM', ?int $referenceId = null): int
    {
        $notificationId = 0;
        $allowedTypes = ['WELCOME', 'OPPORTUNITY', 'REFERRAL_SUBMITTED', 'REFERRAL_ACCEPTED', 'REFERRAL_REJECTED', 'REFERRAL_COMPLETED', 'WALLET_CREDIT', 'SYSTEM'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'SYSTEM';
        }
        try {
            $recipient = db()->prepare('SELECT full_name, email, role FROM users WHERE id = ? LIMIT 1'); $recipient->execute([$userId]); $user = $recipient->fetch();
            if (!$user) {
                throw new RuntimeException('Notification recipient was not found.');
            }
            $userType = canonical_role((string) $user['role']);
            $statement = db()->prepare('INSERT INTO notifications (user_id, user_type, title, message, type, reference_id, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())');
            $statement->execute([$userId, $userType, $title, $body, $type, $referenceId]);
            $notificationId = (int) db()->lastInsertId();
            ActivityLogService::logActivity($userId, $userType, 'Notifications', 'Notification Created', 'Notification', $notificationId, $title);
            if (filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                try {
                    self::sendEmail($userId, $userType, (string) $user['email'], (string) $user['full_name'], $emailHeading, $emailBody, $link);
                } catch (Throwable $emailException) {
                    app_log('Notification email delivery failed: ' . $emailException->getMessage());
                }
            } else {
                app_log('Notification email skipped because the recipient email is invalid.');
            }
        } catch (Throwable $exception) { app_log('Notification creation failed: ' . $exception->getMessage()); }
        return $notificationId;
    }

    public static function opportunityCreated(array $opportunity, array $products, string $businessName): void
    {
        $referrers = db()->query("SELECT u.id FROM users u JOIN referrer_profiles p ON p.user_id = u.id WHERE LOWER(u.role) = 'referrer' AND p.is_profile_completed = 1")->fetchAll(PDO::FETCH_COLUMN);
        $productLines = implode('<br>', array_map(static fn(array $product): string => e($product['name']) . ' — ' . e((string) $product['rate']) . '% commission', $products));
        $lastDate = !empty($opportunity['valid_until']) ? date('d M Y', strtotime((string) $opportunity['valid_until'])) : 'Not specified';
        $link = self::absoluteUrl('referrer/dashboard.php');
        foreach ($referrers as $referrerId) self::notifyUser((int) $referrerId, 'New referral opportunity', 'New campaign: ' . $opportunity['title'] . ' from ' . $businessName . '.', 'New referral opportunity', '<p><strong>' . e($businessName) . '</strong> has launched a new campaign.</p><p><strong>Opportunity:</strong> ' . e($opportunity['title']) . '<br><strong>Category:</strong> ' . e($opportunity['category']) . '<br><strong>Reward details:</strong><br>' . $productLines . '<br><strong>Last date:</strong> ' . e($lastDate) . '</p>', $link, 'OPPORTUNITY', (int) $opportunity['id']);
    }

    public static function referralSubmitted(int $businessId, array $referral, string $referrerName): void
    {
        $link = self::absoluteUrl('business/referral_view.php?id=' . (int) $referral['id']);
        self::notifyUser($businessId, 'New referral submitted', 'New referral from ' . $referrerName . ' for ' . $referral['campaign'] . '.', 'New referral to review', '<p><strong>' . e($referrerName) . '</strong> submitted a customer referral.</p><p><strong>Customer:</strong> ' . e($referral['customer_name']) . '<br><strong>Product:</strong> ' . e($referral['product']) . '<br><strong>Campaign:</strong> ' . e($referral['campaign']) . '<br><strong>Submitted:</strong> ' . e($referral['submitted_at']) . '</p>', $link, 'REFERRAL_SUBMITTED', (int) $referral['id']);
    }

    public static function referralStatus(int $referrerId, string $status, array $referral): void
    {
        $link = self::absoluteUrl('referrer/referral_view.php?id=' . (int) $referral['id']);
        $candidate = (string) ($referral['customer_name'] ?? 'Your candidate');
        $message = $status === 'Accepted'
            ? 'Great news — your referral has been accepted.'
            : ($status === 'Rejected' ? 'Your referral was not accepted on this occasion.' : 'Your referral status has changed.');
        $type = $status === 'Accepted' ? 'REFERRAL_ACCEPTED' : ($status === 'Rejected' ? 'REFERRAL_REJECTED' : 'SYSTEM');
        self::notifyUser($referrerId, 'Referral ' . $status, 'Campaign: ' . $referral['campaign'] . ' | Product: ' . $referral['product'] . ' | Status: ' . $status, 'Referral ' . $status, '<p>' . e($message) . '</p><p><strong>Candidate:</strong> ' . e($candidate) . '<br><strong>Opportunity:</strong> ' . e($referral['campaign']) . '<br><strong>Product:</strong> ' . e($referral['product']) . '</p>', $link, $type, (int) $referral['id']);
    }

    public static function referralCompletedAndWalletCredited(int $referrerId, array $referral, float $grossCommission, float $walletBalance): void
    {
        $platformFee = (float) ($referral['platform_fee'] ?? round($grossCommission * 0.02, 2));
        $netCommission = (float) ($referral['net_commission'] ?? round($grossCommission - $platformFee, 2));
        $link = self::absoluteUrl('referrer/referral_view.php?id=' . (int) $referral['id']);
        $details = '<p><strong>Candidate:</strong> ' . e((string) ($referral['customer_name'] ?? 'Your candidate')) . '<br><strong>Opportunity:</strong> ' . e($referral['campaign']) . '<br><strong>Product:</strong> ' . e($referral['product']) . '<br><strong>Sale amount:</strong> ' . e(opportunity_money($referral['sale_amount'])) . '<br><strong>Gross Commission:</strong> ' . e(opportunity_money($grossCommission)) . '<br><strong>Platform Fee (2%):</strong> ' . e(opportunity_money($platformFee)) . '<br><strong>Net Reward Credited:</strong> ' . e(opportunity_money($netCommission)) . '<br><strong>Wallet balance:</strong> ' . e(opportunity_money($walletBalance)) . '</p>';
        self::notifyUser($referrerId, 'Referral Completed', 'Your referral was completed. Gross: ' . opportunity_money($grossCommission) . '. Platform fee: ' . opportunity_money($platformFee) . '. Net credited: ' . opportunity_money($netCommission) . '.', 'Referral completed', '<p>Your referral has been completed.</p>' . $details, $link, 'REFERRAL_COMPLETED', (int) $referral['id']);
        self::notifyUser($referrerId, 'Wallet Credited', opportunity_money($netCommission) . ' was credited to your wallet.', 'Wallet credited', '<p>Your net commission has been credited to your wallet.</p>' . $details . '<p><strong>Transaction date:</strong> ' . e(date('d M Y, h:i A')) . '</p>', self::absoluteUrl('referrer/wallet.php'), 'WALLET_CREDIT', (int) $referral['id']);
    }

    private static function sendEmail(int $userId, string $userType, string $address, string $name, string $subject, string $body, string $link): void
    {
        (new EmailService())->send(
            $address,
            $name,
            $subject . ' | ' . APP_NAME,
            self::emailTemplate($subject, $body, $link),
            trim(strip_tags($subject . "\n\n" . $body . ($link ? "\n" . $link : '')))
        );
        ActivityLogService::logActivity($userId, $userType, 'Notifications', 'Email Sent', 'Email', null, 'Email notification sent: ' . $subject . '.');
    }

    private static function emailTemplate(string $heading, string $body, string $link): string
    {
        $button = $link ? '<p style="margin:28px 0"><a href="' . e($link) . '" style="background:#2457d6;color:#fff;text-decoration:none;padding:12px 18px;border-radius:6px">View details</a></p>' : '';
        $supportEmail = (string) (getenv('SMTP_FROM_EMAIL') ?: 'support@example.com');
        return '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="margin:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#1e2b43"><table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:28px 12px"><table width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#fff;border-radius:10px;overflow:hidden"><tr><td style="background:#1f4fbe;color:#fff;padding:24px;font-size:20px;font-weight:bold">' . e(APP_NAME) . '</td></tr><tr><td style="padding:30px"><h1 style="font-size:22px;margin-top:0">' . e($heading) . '</h1>' . $body . $button . '<p style="font-size:12px;color:#718096;margin-top:32px">You received this update because you have an account on ' . e(APP_NAME) . '.<br>Need help? Contact <a href="mailto:' . e($supportEmail) . '" style="color:#2457d6">' . e($supportEmail) . '</a>.</p></td></tr></table></td></tr></table></body></html>';
    }

    private static function absoluteUrl(string $path): string { return absolute_url($path); }

    public static function publicEmailTemplate(string $heading, string $body, string $link): string
    {
        return self::emailTemplate($heading, $body, $link);
    }
}
