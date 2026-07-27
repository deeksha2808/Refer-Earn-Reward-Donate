<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/customer_referrals.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/notifications.php';
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
    $orderId = trim((string) ($_POST['razorpay_order_id'] ?? ''));
    $paymentId = trim((string) ($_POST['razorpay_payment_id'] ?? ''));
    $signature = trim((string) ($_POST['razorpay_signature'] ?? ''));
    $method = trim((string) ($_POST['payment_method'] ?? 'unknown'));
    $saleAmount = (float) ($_POST['sale_amount'] ?? 0);
    $invoiceNumber = trim((string) ($_POST['invoice_number'] ?? ''));
    $saleDate = trim((string) ($_POST['sale_date'] ?? ''));
    $completionNotes = trim((string) ($_POST['completion_notes'] ?? ''));

    if ($orderId === '' || $paymentId === '' || $signature === '') {
        throw new RuntimeException('Payment verification data is incomplete.');
    }

    // Verify and mark payment as paid
    $payment = complete_commission_payment($orderId, $paymentId, $signature, $method);

    // Record platform revenue
    record_platform_revenue((int) $payment['id'], (int) $payment['business_id'], (int) $payment['referral_id'], (float) $payment['platform_fee']);

    // Now complete the referral and credit wallet (credits NET commission)
    $completionData = [
        'sale_amount' => (string) $saleAmount,
        'invoice_number' => $invoiceNumber,
        'sale_date' => $saleDate,
        'completion_notes' => $completionNotes,
    ];

    $netCommission = complete_referral_with_commission(
        (int) $payment['referral_id'],
        (int) $user['id'],
        $completionData
    );

    ActivityLogService::logActivity((int) $user['id'], 'BUSINESS', 'Payment', 'Commission Paid via Razorpay', 'Referral', (int) $payment['referral_id'], 'Payment ' . $paymentId . '. Gross: ' . opportunity_money($payment['gross_commission']) . '. Platform fee: ' . opportunity_money($payment['platform_fee']) . '. Net credited: ' . opportunity_money($netCommission) . '.');

    echo json_encode([
        'success' => true,
        'message' => 'Payment successful! ' . opportunity_money($netCommission) . ' credited to referrer wallet.',
        'payment_id' => $paymentId,
        'net_commission' => $netCommission,
    ]);
} catch (Throwable $e) {
    app_log('Razorpay verify API error: ' . $e->getMessage());
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
