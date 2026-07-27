<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/customer_referrals.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/razorpay_service.php';

$user = current_user();
if (!$user || canonical_role((string) $user['role']) !== 'BUSINESS') {
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
    $referralId = filter_var($_POST['referral_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$referralId) throw new RuntimeException('Invalid referral.');

    $referral = business_referral($referralId, (int) $user['id']);
    if (!$referral || !in_array($referral['status'], ['Accepted', 'Customer Approved'], true)) {
        throw new RuntimeException('Only accepted referrals can be paid.');
    }

    // Check for existing payment
    $existing = get_commission_payment_by_referral($referralId);
    if ($existing && $existing['status'] === 'paid') {
        throw new RuntimeException('This referral has already been paid.');
    }

    $saleAmount = (float) ($_POST['sale_amount'] ?? 0);
    if ($saleAmount <= 0) throw new RuntimeException('Enter a valid sale amount.');

    $commissionPct = (float) $referral['commission_percentage'];
    $grossCommission = round($saleAmount * $commissionPct / 100, 2);
    $platformFee = round($grossCommission * PLATFORM_FEE_PERCENTAGE / 100, 2);
    $netCommission = round($grossCommission - $platformFee, 2);

    if ($netCommission <= 0) throw new RuntimeException('Commission amount is too small.');

    // Create or reuse payment record
    if ($existing && $existing['status'] === 'created') {
        $paymentRecordId = (int) $existing['id'];
    } else {
        $paymentRecordId = create_commission_payment($referralId, (int) $user['id'], (int) $referral['referrer_id'], $grossCommission, $platformFee, $netCommission);
    }

    // Create Razorpay order — Business pays GROSS commission to platform
    $order = razorpay_create_order($grossCommission, 'INR', [
        'referral_id' => (string) $referralId,
        'payment_record_id' => (string) $paymentRecordId,
    ]);

    update_commission_payment_order($paymentRecordId, $order['id']);

    echo json_encode([
        'success' => true,
        'demo_mode' => is_demo_mode(),
        'order_id' => $order['id'],
        'amount' => $order['amount'],
        'currency' => $order['currency'] ?? 'INR',
        'key' => razorpay_key_id(),
        'gross_commission' => $grossCommission,
        'platform_fee' => $platformFee,
        'net_commission' => $netCommission,
        'business_name' => $user['full_name'],
        'business_email' => $user['email'],
        'payment_record_id' => $paymentRecordId,
    ]);
} catch (Throwable $e) {
    app_log('Razorpay order API error: ' . $e->getMessage());
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
