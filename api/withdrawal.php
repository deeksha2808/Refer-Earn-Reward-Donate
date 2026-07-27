<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/referrer_profile.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/razorpay_service.php';
require_once __DIR__ . '/../includes/notifications.php';

$user = current_user();
if (!$user || canonical_role((string) $user['role']) !== 'REFERRER') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    verify_csrf();

    $amount = (float) ($_POST['amount'] ?? 0);
    $mode = trim((string) ($_POST['payout_mode'] ?? 'bank'));

    if ($amount < 100) throw new RuntimeException('Minimum withdrawal amount is ₹100.');
    if (!in_array($mode, ['bank', 'upi'], true)) throw new RuntimeException('Choose bank or UPI.');

    $wallet = referrer_wallet((int) $user['id']);
    if ((int) $wallet['id'] === 0) throw new RuntimeException('Wallet not found.');
    if ($amount > (float) $wallet['current_balance']) throw new RuntimeException('Amount exceeds wallet balance.');

    // Verify bank/UPI details exist
    $profile = referrer_profile((int) $user['id']);
    if (!$profile) throw new RuntimeException('Profile not found.');
    if ($mode === 'bank' && (empty($profile['bank_account_number']) || empty($profile['ifsc_code']))) {
        throw new RuntimeException('Bank account details are incomplete. Update your profile first.');
    }
    if ($mode === 'upi' && empty($profile['upi_id'])) {
        throw new RuntimeException('No UPI ID configured. Update your profile first.');
    }

    // Create withdrawal record
    $withdrawalId = create_withdrawal((int) $user['id'], (int) $wallet['id'], $amount);

    // Process payout via Razorpay
    $result = process_payout_withdrawal($withdrawalId, $mode === 'upi' ? 'UPI' : 'IMPS');

    ActivityLogService::logActivity((int) $user['id'], 'REFERRER', 'Wallet', 'Withdrawal Initiated', 'Withdrawal', $withdrawalId, 'Payout of ' . opportunity_money($amount) . ' initiated via ' . strtoupper($mode) . '.');

    try {
        NotificationService::notifyUser((int) $user['id'], 'Withdrawal Initiated', 'Your withdrawal of ' . opportunity_money($amount) . ' is being processed.', 'Withdrawal initiated', '<p>Your withdrawal request of <strong>' . e(opportunity_money($amount)) . '</strong> has been submitted.</p><p><strong>Mode:</strong> ' . e(strtoupper($mode)) . '<br><strong>Reference:</strong> ' . e($result['reference']) . '<br><strong>Status:</strong> ' . e(ucfirst($result['status'])) . '</p><p>You will be notified when the transfer completes.</p>', absolute_url('referrer/wallet.php'), 'SYSTEM');
    } catch (Throwable $e) {
        app_log('Withdrawal notification failed: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Withdrawal of ' . opportunity_money($amount) . ' initiated via ' . strtoupper($mode) . '. Transfer is processing.',
        'reference' => $result['reference'],
        'payout_id' => $result['payout_id'],
        'status' => $result['status'],
    ]);
} catch (Throwable $e) {
    app_log('Withdrawal API error: ' . $e->getMessage());
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
