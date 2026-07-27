<?php
declare(strict_types=1);

const DONATION_CAUSES = ['Education', 'Child Welfare', 'Medical Help', 'Animal Rescue', 'Environmental Protection', 'Old Age Home'];

const PLATFORM_FEE_PERCENTAGE = 2.0;

function complete_referral_with_commission(int $referralId, int $businessId, array $completion): float
{
    require_once __DIR__ . '/customer_referrals.php';
    require_once __DIR__ . '/notifications.php';
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare("SELECT r.*, o.title AS opportunity_title FROM customer_referrals r JOIN referral_opportunities o ON o.id = r.opportunity_id WHERE r.id = ? AND r.business_id = ? LIMIT 1 FOR UPDATE");
        $statement->execute([$referralId, $businessId]);
        $referral = $statement->fetch();
        if (!$referral || !in_array($referral['status'], ['Accepted', 'Customer Approved'], true)) throw new RuntimeException('Only an accepted referral can be completed.');
        if ($referral['commission_percentage'] === null || (float) $referral['commission_percentage'] < 0) throw new RuntimeException('This referral does not have a valid commission snapshot.');

        $saleAmount = (float) $completion['sale_amount'];
        $grossCommission = round($saleAmount * (float) $referral['commission_percentage'] / 100, 2);
        if ($grossCommission <= 0) throw new RuntimeException('Calculated commission must be greater than zero.');

        // Platform service fee deduction
        $platformFee = round($grossCommission * PLATFORM_FEE_PERCENTAGE / 100, 2);
        $netCommission = round($grossCommission - $platformFee, 2);

        $statement = $pdo->prepare("UPDATE customer_referrals SET status = 'Completed', sale_amount = ?, calculated_commission = ?, platform_fee = ?, net_commission = ?, invoice_number = ?, sale_date = ?, completion_notes = ?, completed_at = NOW() WHERE id = ? AND business_id = ? AND status IN ('Accepted', 'Customer Approved')");
        $statement->execute([$saleAmount, $grossCommission, $platformFee, $netCommission, $completion['invoice_number'] ?: null, $completion['sale_date'] ?: null, $completion['completion_notes'] ?: null, $referralId, $businessId]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('The referral could not be completed.');

        $wallet = ensure_wallet((int) $referral['referrer_id']);
        $balanceAfter = (float) $wallet['current_balance'] + $netCommission;
        $statement = $pdo->prepare('UPDATE wallets SET current_balance = current_balance + ?, total_earned = total_earned + ?, total_rewards = total_rewards + ? WHERE id = ?');
        $statement->execute([$netCommission, $netCommission, $netCommission, $wallet['id']]);
        $statement = $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, referral_id, transaction_type, amount, balance_after, description) VALUES (?, ?, 'Reward Credit', ?, ?, ?)");
        $statement->execute([(int) $wallet['id'], $referralId, $netCommission, $balanceAfter, 'Net commission credited for ' . ($referral['product_name'] ?: 'referral') . ' · ' . $referral['opportunity_title'] . ' (Gross: ' . opportunity_money($grossCommission) . ', Platform fee: ' . opportunity_money($platformFee) . ')']);

        add_referral_history($referralId, 'Completed', 'Final sale amount: ' . opportunity_money($saleAmount) . '. Gross commission: ' . opportunity_money($grossCommission) . '. Platform fee (2%): ' . opportunity_money($platformFee) . '. Net credited: ' . opportunity_money($netCommission) . '.');
        $pdo->commit();
        ActivityLogService::logActivity($businessId, 'BUSINESS', 'Wallet', 'Commission Credited', 'Referral', $referralId, 'Gross commission: ' . opportunity_money($grossCommission) . '. Platform fee (2%): ' . opportunity_money($platformFee) . '. Net ' . opportunity_money($netCommission) . ' credited to referrer wallet.');
        ActivityLogService::logActivity($businessId, 'BUSINESS', 'Wallet', 'Platform Fee Deducted', 'Referral', $referralId, 'Platform service fee of ' . opportunity_money($platformFee) . ' (2%) deducted from gross commission of ' . opportunity_money($grossCommission) . '.');
        try { NotificationService::referralCompletedAndWalletCredited((int) $referral['referrer_id'], ['id'=>$referralId,'customer_name'=>$referral['customer_name'],'campaign'=>$referral['opportunity_title'],'product'=>$referral['product_name'] ?: 'Legacy referral','sale_amount'=>$saleAmount,'commission_percentage'=>$referral['commission_percentage'],'platform_fee'=>$platformFee,'net_commission'=>$netCommission], $grossCommission, $balanceAfter); }
        catch (Throwable $notificationException) { app_log('Referral completion notification failed: ' . $notificationException->getMessage()); }
        return $netCommission;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function wallet_empty(): array
{
    return ['id' => 0, 'referrer_id' => 0, 'current_balance' => '0.00', 'total_earned' => '0.00', 'total_rewards' => '0.00', 'total_donated' => '0.00'];
}

function referrer_wallet(int $referrerId): array
{
    $statement = db()->prepare('SELECT * FROM wallets WHERE referrer_id = ? LIMIT 1');
    $statement->execute([$referrerId]);
    $wallet = $statement->fetch();
    return $wallet ?: array_merge(wallet_empty(), ['referrer_id' => $referrerId]);
}

function ensure_wallet(int $referrerId): array
{
    $statement = db()->prepare('SELECT * FROM wallets WHERE referrer_id = ? LIMIT 1 FOR UPDATE');
    $statement->execute([$referrerId]);
    $wallet = $statement->fetch();
    if ($wallet) {
        return $wallet;
    }

    $statement = db()->prepare('INSERT INTO wallets (referrer_id) VALUES (?)');
    $statement->execute([$referrerId]);

    $statement = db()->prepare('SELECT * FROM wallets WHERE id = ? LIMIT 1 FOR UPDATE');
    $statement->execute([(int) db()->lastInsertId()]);
    return $statement->fetch() ?: array_merge(wallet_empty(), ['referrer_id' => $referrerId]);
}

function credit_referral_reward(int $referralId, ?int $businessId = null): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $sql = "SELECT r.*, o.title AS opportunity_title FROM customer_referrals r JOIN referral_opportunities o ON o.id = r.opportunity_id WHERE r.id = ?" . ($businessId !== null ? ' AND r.business_id = ?' : '') . ' LIMIT 1 FOR UPDATE';
        $params = [$referralId];
        if ($businessId !== null) {
            $params[] = $businessId;
        }
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $referral = $statement->fetch();
        if (!$referral || $referral['status'] !== 'Completed') {
            $pdo->rollBack();
            return false;
        }

        $statement = $pdo->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE referral_id = ? AND transaction_type = 'Reward Credit'");
        $statement->execute([$referralId]);
        if ((int) $statement->fetchColumn() > 0) {
            $pdo->commit();
            return false;
        }

        $wallet = ensure_wallet((int) $referral['referrer_id']);
        // New commission referrals are credited from their immutable sale and rate snapshots.
        $amount = $referral['calculated_commission'] !== null ? (float) $referral['calculated_commission'] : (float) $referral['reward_amount'];
        if ($amount <= 0) {
            throw new RuntimeException('Reward amount must be greater than zero.');
        }

        $balanceAfter = (float) $wallet['current_balance'] + $amount;
        $statement = $pdo->prepare('UPDATE wallets SET current_balance = current_balance + ?, total_earned = total_earned + ?, total_rewards = total_rewards + ? WHERE id = ?');
        $statement->execute([$amount, $amount, $amount, $wallet['id']]);

        $statement = $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, referral_id, transaction_type, amount, balance_after, description) VALUES (?, ?, 'Reward Credit', ?, ?, ?)");
        $description = $referral['product_name'] ? 'Commission credited for ' . $referral['product_name'] . ' · ' . $referral['opportunity_title'] : 'Reward credited for ' . $referral['opportunity_title'];
        $statement->execute([(int) $wallet['id'], $referralId, $amount, $balanceAfter, $description]);

        $pdo->commit();
        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function validate_donation(array $input, array $wallet): array
{
    $ngoId = trim((string) ($input['ngo_id'] ?? ''));
    $values = ['cause_name' => trim((string) ($input['cause_name'] ?? '')), 'donation_amount' => trim((string) ($input['donation_amount'] ?? '')), 'message' => trim((string) ($input['message'] ?? '')), 'ngo_id' => $ngoId === '' ? null : (int) $ngoId];
    $errors = [];
    // Accept DONATION_CAUSES or any non-empty cause when NGO is selected (NGO category serves as cause)
    if ($values['cause_name'] === '' || mb_strlen($values['cause_name']) > 100) {
        $errors['cause_name'] = 'Choose a valid cause.';
    } elseif ($values['ngo_id'] === null && !in_array($values['cause_name'], DONATION_CAUSES, true)) {
        $errors['cause_name'] = 'Choose a valid cause.';
    }
    if (!is_numeric($values['donation_amount']) || (float) $values['donation_amount'] <= 0) {
        $errors['donation_amount'] = 'Enter a donation amount greater than zero.';
    } elseif ((float) $values['donation_amount'] > (float) $wallet['current_balance']) {
        $errors['donation_amount'] = 'Donation amount cannot exceed wallet balance.';
    }
    if (mb_strlen($values['message']) > 500) {
        $errors['message'] = 'Keep the message to 500 characters or fewer.';
    }
    // validate NGO if provided
    if ($values['ngo_id'] !== null) {
        require_once __DIR__ . '/ngos.php';
        if (!ngo_exists($values['ngo_id'])) $errors['ngo_id'] = 'Choose a valid NGO.';
    }
    return [$values, $errors];
}

function create_donation(int $referrerId, array $values): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $wallet = ensure_wallet($referrerId);
        $amount = (float) $values['donation_amount'];
        if ($amount <= 0 || $amount > (float) $wallet['current_balance']) {
            throw new RuntimeException('Donation amount cannot exceed wallet balance.');
        }

        $balanceAfter = (float) $wallet['current_balance'] - $amount;
        $statement = $pdo->prepare('UPDATE wallets SET current_balance = current_balance - ?, total_donated = total_donated + ? WHERE id = ?');
        $statement->execute([$amount, $amount, $wallet['id']]);

        $statement = $pdo->prepare("INSERT INTO donations (wallet_id, cause_name, donation_amount, message, ngo_id, status) VALUES (?, ?, ?, ?, ?, 'Completed')");
        $statement->execute([(int) $wallet['id'], $values['cause_name'], $amount, $values['message'] !== '' ? $values['message'] : null, $values['ngo_id'] !== null ? $values['ngo_id'] : null]);

        $statement = $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, transaction_type, amount, balance_after, description) VALUES (?, 'Donation', ?, ?, ?)");
        $statement->execute([(int) $wallet['id'], $amount, $balanceAfter, 'Donation to ' . $values['cause_name']]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function referrer_wallet_stats(int $referrerId): array
{
    $wallet = referrer_wallet($referrerId);
    $statement = db()->prepare("SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE wallet_id = ? AND transaction_type = 'Reward Credit' AND DATE(created_at) = CURDATE()");
    $statement->execute([(int) $wallet['id']]);
    $todaysEarnings = (float) $statement->fetchColumn();

    $statement = db()->prepare("SELECT COALESCE(SUM(COALESCE(calculated_commission, reward_amount)), 0) FROM customer_referrals WHERE referrer_id = ? AND status IN ('Submitted', 'Under Review', 'Processing', 'Accepted')");
    $statement->execute([$referrerId]);
    $pendingRewards = (float) $statement->fetchColumn();

    $statement = db()->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(status = 'Completed'), 0) AS completed, COALESCE(SUM(status IN ('Submitted', 'Under Review', 'Processing', 'Accepted')), 0) AS pending FROM customer_referrals WHERE referrer_id = ?");
    $statement->execute([$referrerId]);
    $referrals = $statement->fetch() ?: [];

    return [
        'wallet' => $wallet,
        'todays_earnings' => $todaysEarnings,
        'pending_rewards' => $pendingRewards,
        'total_referrals' => (int) ($referrals['total'] ?? 0),
        'successful_referrals' => (int) ($referrals['completed'] ?? 0),
        'pending_referrals' => (int) ($referrals['pending'] ?? 0),
    ];
}
