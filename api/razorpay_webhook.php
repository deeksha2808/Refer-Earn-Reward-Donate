<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/razorpay_service.php';
require_once __DIR__ . '/../includes/notifications.php';

// Razorpay sends POST with JSON body
$rawBody = file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');

// Verify webhook signature
if ($signature === '' || !razorpay_verify_webhook_signature($rawBody, $signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$event = json_decode($rawBody, true);
if (!$event || !isset($event['event'], $event['payload'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$eventId = $event['account_id'] . '_' . ($event['payload']['payout']['entity']['id'] ?? '') . '_' . $event['event'];
$eventType = $event['event'];

// Prevent duplicate processing
if (webhook_event_processed($eventId)) {
    http_response_code(200);
    echo json_encode(['status' => 'already_processed']);
    exit;
}

try {
    $payout = $event['payload']['payout']['entity'] ?? null;
    if (!$payout || !isset($payout['id'])) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored']);
        exit;
    }

    $payoutId = $payout['id'];
    $utr = $payout['utr'] ?? null;
    $failureReason = $payout['failure_reason'] ?? null;

    // Find the withdrawal by payout_id
    $stmt = db()->prepare('SELECT * FROM withdrawals WHERE razorpay_payout_id = ? LIMIT 1');
    $stmt->execute([$payoutId]);
    $withdrawal = $stmt->fetch();

    if (!$withdrawal) {
        record_webhook_event($eventId, $eventType, $payoutId, $rawBody);
        http_response_code(200);
        echo json_encode(['status' => 'no_matching_withdrawal']);
        exit;
    }

    $withdrawalId = (int) $withdrawal['id'];
    $referrerId = (int) $withdrawal['referrer_id'];

    switch ($eventType) {
        case 'payout.processed':
            db()->prepare("UPDATE withdrawals SET status = 'processed', utr_number = ?, processed_at = NOW(), completed_at = NOW() WHERE id = ? AND status IN ('pending','processing')")
                ->execute([$utr, $withdrawalId]);
            try {
                NotificationService::notifyUser($referrerId, 'Withdrawal Successful', 'Your withdrawal of ' . opportunity_money($withdrawal['amount']) . ' has been processed. UTR: ' . ($utr ?: 'N/A'), 'Withdrawal processed', '<p>Your withdrawal has been successfully processed.</p><p><strong>Amount:</strong> ' . e(opportunity_money($withdrawal['amount'])) . '<br><strong>UTR:</strong> ' . e($utr ?: 'N/A') . '</p>', absolute_url('referrer/wallet.php'), 'SYSTEM');
            } catch (Throwable $e) { app_log('Webhook notification error: ' . $e->getMessage()); }
            break;

        case 'payout.failed':
            // Refund wallet
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE withdrawals SET status = 'failed', failure_reason = ? WHERE id = ? AND status IN ('pending','processing')")
                    ->execute([$failureReason ?: 'Payout failed', $withdrawalId]);
                $pdo->prepare('UPDATE wallets SET current_balance = current_balance + ? WHERE id = ?')
                    ->execute([$withdrawal['amount'], $withdrawal['wallet_id']]);
                $wallet = $pdo->prepare('SELECT current_balance FROM wallets WHERE id = ?');
                $wallet->execute([(int) $withdrawal['wallet_id']]);
                $newBalance = (float) $wallet->fetchColumn();
                $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, transaction_type, amount, balance_after, description) VALUES (?, 'Adjustment', ?, ?, ?)")
                    ->execute([(int) $withdrawal['wallet_id'], $withdrawal['amount'], $newBalance, 'Refund: Payout #' . $withdrawalId . ' failed']);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            try {
                NotificationService::notifyUser($referrerId, 'Withdrawal Failed', 'Your withdrawal of ' . opportunity_money($withdrawal['amount']) . ' failed. Amount refunded to wallet.', 'Withdrawal failed', '<p>Your withdrawal could not be processed.</p><p><strong>Amount:</strong> ' . e(opportunity_money($withdrawal['amount'])) . '<br><strong>Reason:</strong> ' . e($failureReason ?: 'Unknown') . '</p><p>The amount has been refunded to your wallet.</p>', absolute_url('referrer/wallet.php'), 'SYSTEM');
            } catch (Throwable $e) { app_log('Webhook notification error: ' . $e->getMessage()); }
            break;

        case 'payout.reversed':
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE withdrawals SET status = 'reversed', failure_reason = ? WHERE id = ?")->execute(['Payout reversed', $withdrawalId]);
                $pdo->prepare('UPDATE wallets SET current_balance = current_balance + ? WHERE id = ?')->execute([$withdrawal['amount'], $withdrawal['wallet_id']]);
                $wallet = $pdo->prepare('SELECT current_balance FROM wallets WHERE id = ?');
                $wallet->execute([(int) $withdrawal['wallet_id']]);
                $newBalance = (float) $wallet->fetchColumn();
                $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, transaction_type, amount, balance_after, description) VALUES (?, 'Adjustment', ?, ?, ?)")
                    ->execute([(int) $withdrawal['wallet_id'], $withdrawal['amount'], $newBalance, 'Refund: Payout #' . $withdrawalId . ' reversed']);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            try {
                NotificationService::notifyUser($referrerId, 'Withdrawal Reversed', 'Your withdrawal of ' . opportunity_money($withdrawal['amount']) . ' was reversed. Amount refunded.', 'Withdrawal reversed', '<p>Your withdrawal was reversed by the bank.</p><p>The amount has been refunded to your wallet.</p>', absolute_url('referrer/wallet.php'), 'SYSTEM');
            } catch (Throwable $e) { app_log('Webhook notification error: ' . $e->getMessage()); }
            break;

        case 'payout.cancelled':
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE withdrawals SET status = 'cancelled', failure_reason = 'Cancelled' WHERE id = ?")->execute([$withdrawalId]);
                $pdo->prepare('UPDATE wallets SET current_balance = current_balance + ? WHERE id = ?')->execute([$withdrawal['amount'], $withdrawal['wallet_id']]);
                $wallet = $pdo->prepare('SELECT current_balance FROM wallets WHERE id = ?');
                $wallet->execute([(int) $withdrawal['wallet_id']]);
                $newBalance = (float) $wallet->fetchColumn();
                $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, transaction_type, amount, balance_after, description) VALUES (?, 'Adjustment', ?, ?, ?)")
                    ->execute([(int) $withdrawal['wallet_id'], $withdrawal['amount'], $newBalance, 'Refund: Payout #' . $withdrawalId . ' cancelled']);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            break;
    }

    record_webhook_event($eventId, $eventType, $payoutId, $rawBody);
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    app_log('Webhook processing error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Processing failed']);
}
