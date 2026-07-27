<?php
declare(strict_types=1);

function payment_mode(): string
{
    return strtolower(trim((string) getenv('PAYMENT_MODE'))) ?: 'demo';
}

function is_demo_mode(): bool
{
    return payment_mode() !== 'live';
}

function razorpay_key_id(): string
{
    return (string) getenv('RAZORPAY_KEY_ID');
}

function razorpay_key_secret(): string
{
    return (string) getenv('RAZORPAY_KEY_SECRET');
}

function razorpay_create_order(float $amount, string $currency = 'INR', array $notes = []): array
{
    if (is_demo_mode()) {
        return [
            'id' => 'order_demo_' . bin2hex(random_bytes(10)),
            'amount' => (int) round($amount * 100),
            'currency' => $currency,
            'status' => 'created',
        ];
    }

    $keyId = razorpay_key_id();
    $keySecret = razorpay_key_secret();
    if ($keyId === '' || $keySecret === '') {
        throw new RuntimeException('Razorpay credentials are not configured.');
    }

    $amountPaise = (int) round($amount * 100);
    $payload = json_encode([
        'amount' => $amountPaise,
        'currency' => $currency,
        'notes' => $notes ?: new \stdClass(),
    ]);

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_USERPWD => $keyId . ':' . $keySecret,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        app_log('Razorpay order creation failed: HTTP ' . $httpCode . ' ' . ($response ?: 'no response'));
        throw new RuntimeException('Payment order could not be created. Please try again.');
    }

    $data = json_decode($response, true);
    if (!isset($data['id'])) {
        app_log('Razorpay order response missing id: ' . $response);
        throw new RuntimeException('Payment order response was invalid.');
    }

    return $data;
}

function razorpay_verify_signature(string $orderId, string $paymentId, string $signature): bool
{
    if (is_demo_mode()) {
        return str_starts_with($orderId, 'order_demo_') && str_starts_with($paymentId, 'pay_demo_');
    }
    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, razorpay_key_secret());
    return hash_equals($expected, $signature);
}

function razorpay_fetch_payment(string $paymentId): array
{
    $keyId = razorpay_key_id();
    $keySecret = razorpay_key_secret();

    $ch = curl_init('https://api.razorpay.com/v1/payments/' . $paymentId);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_USERPWD => $keyId . ':' . $keySecret,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        throw new RuntimeException('Could not fetch payment details.');
    }

    return json_decode($response, true) ?: [];
}

// Commission payment functions

function create_commission_payment(int $referralId, int $businessId, int $referrerId, float $gross, float $fee, float $net): int
{
    // Prevent duplicate
    $stmt = db()->prepare('SELECT id FROM commission_payments WHERE referral_id = ? LIMIT 1');
    $stmt->execute([$referralId]);
    if ($stmt->fetchColumn()) {
        throw new RuntimeException('A payment record already exists for this referral.');
    }

    $stmt = db()->prepare('INSERT INTO commission_payments (referral_id, business_id, referrer_id, gross_commission, platform_fee, net_commission, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$referralId, $businessId, $referrerId, $gross, $fee, $net, 'created']);
    return (int) db()->lastInsertId();
}

function update_commission_payment_order(int $paymentRecordId, string $orderId): void
{
    $stmt = db()->prepare('UPDATE commission_payments SET razorpay_order_id = ? WHERE id = ?');
    $stmt->execute([$orderId, $paymentRecordId]);
}

function complete_commission_payment(string $orderId, string $paymentId, string $signature, string $method): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM commission_payments WHERE razorpay_order_id = ? AND status = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$orderId, 'created']);
        $payment = $stmt->fetch();
        if (!$payment) throw new RuntimeException('Payment record not found or already processed.');

        if (!razorpay_verify_signature($orderId, $paymentId, $signature)) {
            $pdo->prepare('UPDATE commission_payments SET status = ?, razorpay_payment_id = ? WHERE id = ?')->execute(['failed', $paymentId, $payment['id']]);
            $pdo->commit();
            throw new RuntimeException('Payment signature verification failed.');
        }

        $pdo->prepare('UPDATE commission_payments SET razorpay_payment_id = ?, razorpay_signature = ?, payment_method = ?, status = ?, paid_at = NOW() WHERE id = ?')
            ->execute([$paymentId, $signature, $method, 'paid', $payment['id']]);

        $pdo->commit();
        return $payment;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function get_commission_payment_by_referral(int $referralId): ?array
{
    $stmt = db()->prepare('SELECT * FROM commission_payments WHERE referral_id = ? LIMIT 1');
    $stmt->execute([$referralId]);
    return $stmt->fetch() ?: null;
}

function record_platform_revenue(int $paymentId, int $businessId, int $referralId, float $platformFee): void
{
    $stmt = db()->prepare('INSERT INTO platform_revenue (payment_id, business_id, referral_id, platform_fee) VALUES (?, ?, ?, ?)');
    $stmt->execute([$paymentId, $businessId, $referralId, $platformFee]);
}

function platform_revenue_stats(): array
{
    $stmt = db()->query("SELECT COUNT(*) AS total_payments, COALESCE(SUM(platform_fee), 0) AS total_revenue FROM platform_revenue");
    return $stmt->fetch() ?: ['total_payments' => 0, 'total_revenue' => 0];
}

// Withdrawal functions

function create_withdrawal(int $referrerId, int $walletId, float $amount): int
{
    if ($amount < 100) throw new RuntimeException('Minimum withdrawal amount is ₹100.');

    $wallet = db()->prepare('SELECT current_balance FROM wallets WHERE id = ? LIMIT 1');
    $wallet->execute([$walletId]);
    $balance = (float) ($wallet->fetchColumn() ?: 0);
    if ($amount > $balance) throw new RuntimeException('Withdrawal amount exceeds wallet balance.');

    $stmt = db()->prepare('INSERT INTO withdrawals (referrer_id, wallet_id, amount, status) VALUES (?, ?, ?, ?)');
    $stmt->execute([$referrerId, $walletId, $amount, 'pending']);
    return (int) db()->lastInsertId();
}

function process_withdrawal(int $withdrawalId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT w.*, wl.current_balance FROM withdrawals w JOIN wallets wl ON wl.id = w.wallet_id WHERE w.id = ? AND w.status = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$withdrawalId, 'pending']);
        $withdrawal = $stmt->fetch();
        if (!$withdrawal) throw new RuntimeException('Withdrawal not found or already processed.');

        if ((float) $withdrawal['amount'] > (float) $withdrawal['current_balance']) {
            $pdo->prepare('UPDATE withdrawals SET status = ?, failure_reason = ? WHERE id = ?')->execute(['failed', 'Insufficient balance', $withdrawalId]);
            $pdo->commit();
            throw new RuntimeException('Insufficient wallet balance for withdrawal.');
        }

        // Deduct from wallet
        $balanceAfter = (float) $withdrawal['current_balance'] - (float) $withdrawal['amount'];
        $pdo->prepare('UPDATE wallets SET current_balance = current_balance - ? WHERE id = ?')->execute([$withdrawal['amount'], $withdrawal['wallet_id']]);

        // Record transaction
        $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, transaction_type, amount, balance_after, description) VALUES (?, 'Withdrawal', ?, ?, ?)")
            ->execute([(int) $withdrawal['wallet_id'], $withdrawal['amount'], $balanceAfter, 'Wallet withdrawal #' . $withdrawalId]);

        // Mark completed
        $ref = 'WD-' . str_pad((string) $withdrawalId, 8, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE withdrawals SET status = ?, reference_number = ?, completed_at = NOW() WHERE id = ?')->execute(['completed', $ref, $withdrawalId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function referrer_withdrawals(int $referrerId): array
{
    $stmt = db()->prepare('SELECT * FROM withdrawals WHERE referrer_id = ? ORDER BY created_at DESC');
    $stmt->execute([$referrerId]);
    return $stmt->fetchAll();
}

function referrer_withdrawal_stats(int $referrerId): array
{
    $stmt = db()->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN status IN ('completed','processed') THEN amount END), 0) AS total_withdrawn, COALESCE(SUM(CASE WHEN status IN ('pending','processing') THEN amount END), 0) AS pending_amount, COALESCE(SUM(status IN ('completed','processed')), 0) AS successful, COALESCE(SUM(status IN ('pending','processing')), 0) AS pending_count, COALESCE(SUM(status='failed'), 0) AS failed FROM withdrawals WHERE referrer_id = ?");
    $stmt->execute([$referrerId]);
    return $stmt->fetch() ?: ['total' => 0, 'total_withdrawn' => 0, 'pending_amount' => 0, 'successful' => 0, 'pending_count' => 0, 'failed' => 0];
}

function business_commission_stats(int $businessId): array
{
    $stmt = db()->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN status='paid' THEN net_commission END), 0) AS total_paid, COALESCE(SUM(CASE WHEN status='paid' THEN platform_fee END), 0) AS total_platform_fees, COALESCE(SUM(status='paid'), 0) AS successful, COALESCE(SUM(status='created'), 0) AS pending, COALESCE(SUM(status='failed'), 0) AS failed FROM commission_payments WHERE business_id = ?");
    $stmt->execute([$businessId]);
    return $stmt->fetch() ?: ['total' => 0, 'total_paid' => 0, 'total_platform_fees' => 0, 'successful' => 0, 'pending' => 0, 'failed' => 0];
}

// =============================================
// Razorpay Payouts (RazorpayX)
// =============================================

function razorpay_api_request(string $method, string $endpoint, ?array $payload = null): array
{
    $keyId = razorpay_key_id();
    $keySecret = razorpay_key_secret();
    if ($keyId === '' || $keySecret === '') {
        throw new RuntimeException('Razorpay credentials are not configured.');
    }

    $url = 'https://api.razorpay.com/v1/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    $opts = [
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_USERPWD => $keyId . ':' . $keySecret,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload ?: new \stdClass());
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Razorpay API request failed (network error).');
    }

    $data = json_decode($response, true) ?: [];
    if ($httpCode >= 400) {
        $errMsg = $data['error']['description'] ?? ($data['message'] ?? 'Unknown API error');
        app_log("Razorpay API error [{$method} {$endpoint}] HTTP {$httpCode}: {$response}");
        throw new RuntimeException('Razorpay: ' . $errMsg);
    }

    return $data;
}

function razorpay_create_contact(int $userId, string $name, string $email, string $phone): string
{
    $data = razorpay_api_request('POST', 'contacts', [
        'name' => $name,
        'email' => $email,
        'contact' => $phone,
        'type' => 'employee',
        'reference_id' => 'referrer_' . $userId,
        'notes' => ['platform' => APP_NAME, 'user_id' => (string) $userId],
    ]);
    if (!isset($data['id'])) throw new RuntimeException('Failed to create Razorpay contact.');
    return $data['id'];
}

function razorpay_create_fund_account_bank(string $contactId, string $accountName, string $accountNumber, string $ifsc): string
{
    $data = razorpay_api_request('POST', 'fund_accounts', [
        'contact_id' => $contactId,
        'account_type' => 'bank_account',
        'bank_account' => [
            'name' => $accountName,
            'ifsc' => $ifsc,
            'account_number' => $accountNumber,
        ],
    ]);
    if (!isset($data['id'])) throw new RuntimeException('Failed to create bank fund account.');
    return $data['id'];
}

function razorpay_create_fund_account_vpa(string $contactId, string $vpa): string
{
    $data = razorpay_api_request('POST', 'fund_accounts', [
        'contact_id' => $contactId,
        'account_type' => 'vpa',
        'vpa' => ['address' => $vpa],
    ]);
    if (!isset($data['id'])) throw new RuntimeException('Failed to create VPA fund account.');
    return $data['id'];
}

function razorpay_create_payout(string $fundAccountId, float $amount, string $mode, string $purpose, string $referenceId, array $notes = []): array
{
    $data = razorpay_api_request('POST', 'payouts', [
        'account_number' => (string) getenv('RAZORPAY_ACCOUNT_NUMBER'),
        'fund_account_id' => $fundAccountId,
        'amount' => (int) round($amount * 100),
        'currency' => 'INR',
        'mode' => $mode,
        'purpose' => $purpose,
        'reference_id' => $referenceId,
        'narration' => 'Wallet Withdrawal',
        'notes' => $notes ?: new \stdClass(),
    ]);
    if (!isset($data['id'])) throw new RuntimeException('Failed to create Razorpay payout.');
    return $data;
}

function ensure_razorpay_contact(int $userId): string
{
    require_once __DIR__ . '/referrer_profile.php';
    $profile = referrer_profile($userId);
    if (!$profile) throw new RuntimeException('Referrer profile not found.');

    // Return existing contact if available
    if (!empty($profile['razorpay_contact_id'])) {
        return $profile['razorpay_contact_id'];
    }

    $contactId = razorpay_create_contact(
        $userId,
        $profile['full_name'],
        $profile['email'],
        $profile['mobile_number']
    );

    db()->prepare('UPDATE referrer_profiles SET razorpay_contact_id = ? WHERE user_id = ?')->execute([$contactId, $userId]);
    return $contactId;
}

function ensure_razorpay_fund_account(int $userId, string $mode): string
{
    require_once __DIR__ . '/referrer_profile.php';
    $profile = referrer_profile($userId);
    if (!$profile) throw new RuntimeException('Referrer profile not found.');

    $contactId = ensure_razorpay_contact($userId);

    if ($mode === 'UPI' || $mode === 'vpa') {
        if (empty($profile['upi_id'])) throw new RuntimeException('No UPI ID configured. Update your profile first.');
        if (!empty($profile['razorpay_fund_account_id_vpa'])) return $profile['razorpay_fund_account_id_vpa'];
        $faId = razorpay_create_fund_account_vpa($contactId, $profile['upi_id']);
        db()->prepare('UPDATE referrer_profiles SET razorpay_fund_account_id_vpa = ? WHERE user_id = ?')->execute([$faId, $userId]);
        return $faId;
    }

    // Bank account
    if (empty($profile['bank_account_number']) || empty($profile['ifsc_code'])) {
        throw new RuntimeException('Bank account details are incomplete. Update your profile first.');
    }
    if (!empty($profile['razorpay_fund_account_id_bank'])) return $profile['razorpay_fund_account_id_bank'];
    $faId = razorpay_create_fund_account_bank($contactId, $profile['bank_account_name'], $profile['bank_account_number'], $profile['ifsc_code']);
    db()->prepare('UPDATE referrer_profiles SET razorpay_fund_account_id_bank = ? WHERE user_id = ?')->execute([$faId, $userId]);
    return $faId;
}

function process_payout_withdrawal(int $withdrawalId, string $mode): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT w.*, wl.current_balance, wl.referrer_id FROM withdrawals w JOIN wallets wl ON wl.id = w.wallet_id WHERE w.id = ? AND w.status = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$withdrawalId, 'pending']);
        $withdrawal = $stmt->fetch();
        if (!$withdrawal) throw new RuntimeException('Withdrawal not found or already processed.');

        $amount = (float) $withdrawal['amount'];
        if ($amount > (float) $withdrawal['current_balance']) {
            $pdo->prepare("UPDATE withdrawals SET status = 'failed', failure_reason = 'Insufficient balance' WHERE id = ?")->execute([$withdrawalId]);
            $pdo->commit();
            throw new RuntimeException('Insufficient wallet balance.');
        }

        // Deduct from wallet immediately
        $balanceAfter = (float) $withdrawal['current_balance'] - $amount;
        $pdo->prepare('UPDATE wallets SET current_balance = current_balance - ? WHERE id = ?')->execute([$amount, $withdrawal['wallet_id']]);
        $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, transaction_type, amount, balance_after, description) VALUES (?, 'Withdrawal', ?, ?, ?)")
            ->execute([(int) $withdrawal['wallet_id'], $amount, $balanceAfter, 'Payout withdrawal #' . $withdrawalId]);

        // Mark as processing
        $pdo->prepare("UPDATE withdrawals SET status = 'processing', payment_method = ? WHERE id = ?")->execute([$mode, $withdrawalId]);
        $pdo->commit();

        $refId = 'WD-' . str_pad((string) $withdrawalId, 8, '0', STR_PAD_LEFT);
        $referrerId = (int) $withdrawal['referrer_id'];
        $payoutMode = ($mode === 'UPI' || $mode === 'vpa') ? 'UPI' : 'IMPS';

        if (is_demo_mode()) {
            // Demo mode: simulate successful payout immediately
            $payoutId = 'pout_demo_' . bin2hex(random_bytes(10));
            $utr = 'UTR' . strtoupper(bin2hex(random_bytes(6)));
            db()->prepare("UPDATE withdrawals SET razorpay_payout_id = ?, reference_number = ?, payout_mode = ?, status = 'processed', utr_number = ?, processed_at = NOW(), completed_at = NOW() WHERE id = ?")
                ->execute([$payoutId, $refId, $payoutMode, $utr, $withdrawalId]);
            return ['withdrawal_id' => $withdrawalId, 'payout_id' => $payoutId, 'status' => 'processed', 'reference' => $refId, 'utr' => $utr];
        }

        // Live mode: Create Razorpay payout
        $fundAccountId = ensure_razorpay_fund_account($referrerId, $mode);

        $payout = razorpay_create_payout($fundAccountId, $amount, $payoutMode, 'payout', $refId, [
            'withdrawal_id' => (string) $withdrawalId,
            'referrer_id' => (string) $referrerId,
        ]);

        // Update withdrawal with payout details
        db()->prepare('UPDATE withdrawals SET razorpay_payout_id = ?, razorpay_contact_id = ?, razorpay_fund_account_id = ?, reference_number = ?, payout_mode = ? WHERE id = ?')
            ->execute([$payout['id'], $payout['fund_account']['contact_id'] ?? null, $fundAccountId, $refId, $payoutMode, $withdrawalId]);

        // If payout is immediately processed
        if (($payout['status'] ?? '') === 'processed') {
            db()->prepare("UPDATE withdrawals SET status = 'processed', utr_number = ?, processed_at = NOW() WHERE id = ?")->execute([$payout['utr'] ?? null, $withdrawalId]);
        }

        return ['withdrawal_id' => $withdrawalId, 'payout_id' => $payout['id'], 'status' => $payout['status'] ?? 'processing', 'reference' => $refId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function razorpay_verify_webhook_signature(string $body, string $signature): bool
{
    $webhookSecret = (string) getenv('RAZORPAY_WEBHOOK_SECRET');
    if ($webhookSecret === '') return false;
    $expected = hash_hmac('sha256', $body, $webhookSecret);
    return hash_equals($expected, $signature);
}

function webhook_event_processed(string $eventId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM razorpay_webhook_events WHERE event_id = ?');
    $stmt->execute([$eventId]);
    return (int) $stmt->fetchColumn() > 0;
}

function record_webhook_event(string $eventId, string $eventType, ?string $entityId, ?string $payload): void
{
    db()->prepare('INSERT IGNORE INTO razorpay_webhook_events (event_id, event_type, entity_id, payload) VALUES (?, ?, ?, ?)')
        ->execute([$eventId, $eventType, $entityId, $payload]);
}
