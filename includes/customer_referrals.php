<?php
declare(strict_types=1);

require_once __DIR__ . '/location.php';

const CUSTOMER_REFERRAL_STATUSES = ['Submitted', 'Under Review', 'Processing', 'Waiting for Customer Approval', 'Customer Approved', 'Declined by Customer', 'Accepted', 'Rejected', 'Completed'];
const CUSTOMER_REFERRAL_STATUS_TRANSITIONS = [
    'Submitted' => ['Under Review'],
    'Under Review' => ['Processing', 'Rejected'],
    'Processing' => ['Waiting for Customer Approval', 'Rejected'],
    'Waiting for Customer Approval' => [],
    'Customer Approved' => ['Completed'],
    'Declined by Customer' => [],
    'Accepted' => ['Completed'],
    'Rejected' => [],
    'Completed' => [],
];

/**
 * Referral decisions are deliberately one-way.  In particular, a completed
 * referral cannot be reopened after its wallet credit has been recorded.
 */
function permitted_referral_statuses(string $currentStatus): array
{
    return CUSTOMER_REFERRAL_STATUS_TRANSITIONS[$currentStatus] ?? [];
}

function validate_referral_completion(array $input): array
{
    $values = [
        'sale_amount' => trim((string) ($input['sale_amount'] ?? '')),
        'invoice_number' => trim((string) ($input['invoice_number'] ?? '')),
        'sale_date' => trim((string) ($input['sale_date'] ?? '')),
        'completion_notes' => trim((string) ($input['completion_notes'] ?? '')),
    ];
    $errors = [];
    if (!is_numeric($values['sale_amount']) || (float) $values['sale_amount'] <= 0) $errors['sale_amount'] = 'Enter a final sale amount greater than zero.';
    elseif ((float) $values['sale_amount'] > 999999999999.99) $errors['sale_amount'] = 'Final sale amount is too large.';
    if (mb_strlen($values['invoice_number']) > 100) $errors['invoice_number'] = 'Keep the invoice number to 100 characters or fewer.';
    if ($values['sale_date'] !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $values['sale_date']);
        if (!$date || $date->format('Y-m-d') !== $values['sale_date'] || $values['sale_date'] > (new DateTimeImmutable('today'))->format('Y-m-d')) $errors['sale_date'] = 'Enter a valid sale date that is not in the future.';
    }
    if (mb_strlen($values['completion_notes']) > 5000) $errors['completion_notes'] = 'Keep completion notes to 5,000 characters or fewer.';
    return [$values, $errors];
}

function can_transition_referral_status(string $currentStatus, string $nextStatus): bool
{
    return in_array($nextStatus, permitted_referral_statuses($currentStatus), true);
}

function active_opportunity(int $opportunityId): ?array
{
    $statement = db()->prepare("SELECT o.*, COALESCE(b.business_name, u.full_name) AS business_name, b.logo, COALESCE(b.business_email, u.email) AS business_email, COALESCE(b.business_phone, u.phone) AS business_phone, b.business_address, b.city AS business_city, b.business_description FROM referral_opportunities o JOIN users u ON u.id = o.business_id LEFT JOIN business_profiles b ON b.user_id = o.business_id WHERE o.id = ? AND o.status = 'Active' AND o.valid_until >= CURDATE() LIMIT 1");
    $statement->execute([$opportunityId]);
    return $statement->fetch() ?: null;
}

function referral_defaults(): array
{
    return ['customer_name' => '', 'customer_phone' => '', 'customer_email' => '', 'customer_address' => '', 'customer_city' => '', 'customer_state' => '', 'customer_notes' => '', 'opportunity_product_id' => ''];
}

function validate_customer_referral(array $input): array
{
    $values = referral_defaults();
    foreach ($values as $field => $default) $values[$field] = trim((string) ($input[$field] ?? $default));
    $errors = [];
    if (!filter_var($values['opportunity_product_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) $errors['opportunity_product_id'] = 'Choose a product or service.';
    if (mb_strlen($values['customer_name']) < 2 || mb_strlen($values['customer_name']) > 150) $errors['customer_name'] = 'Enter a customer name between 2 and 150 characters.';
    if (!preg_match('/^[0-9+() .-]{7,25}$/', $values['customer_phone'])) $errors['customer_phone'] = 'Enter a valid customer phone number.';
    if ($values['customer_email'] === '' || !filter_var($values['customer_email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['customer_email']) > 150) $errors['customer_email'] = 'Enter a valid customer email address.';
    if (mb_strlen($values['customer_address']) < 2 || mb_strlen($values['customer_address']) > 255) $errors['customer_address'] = 'Enter an address between 2 and 255 characters.';
    if (mb_strlen($values['customer_city']) < 2 || mb_strlen($values['customer_city']) > 100) $errors['customer_city'] = 'Enter a city between 2 and 100 characters.';
    if (mb_strlen($values['customer_state']) < 2 || mb_strlen($values['customer_state']) > 100) $errors['customer_state'] = 'Enter a state between 2 and 100 characters.';
    if (mb_strlen($values['customer_notes']) > 5000) $errors['customer_notes'] = 'Keep additional notes to 5,000 characters or fewer.';
    return [$values, $errors];
}

function add_referral_history(int $referralId, string $status, ?string $note = null): void
{
    $statement = db()->prepare('INSERT INTO referral_status_history (referral_id, status, note) VALUES (?, ?, ?)');
    $statement->execute([$referralId, $status, $note]);
}

function referral_history(int $referralId): array
{
    $statement = db()->prepare('SELECT status, note, created_at FROM referral_status_history WHERE referral_id = ? ORDER BY created_at, id');
    $statement->execute([$referralId]);
    return $statement->fetchAll();
}

function referrer_referral(int $referralId, int $referrerId): ?array
{
    $statement = db()->prepare('SELECT r.*, o.title AS opportunity_title, o.category, o.service_location, o.description AS opportunity_description, o.valid_until, COALESCE(b.business_name, u.full_name) AS business_name, b.logo, COALESCE(b.business_email, u.email) AS business_email, COALESCE(b.business_phone, u.phone) AS business_phone, b.business_address, b.city AS business_city FROM customer_referrals r JOIN referral_opportunities o ON o.id = r.opportunity_id JOIN users u ON u.id = r.business_id LEFT JOIN business_profiles b ON b.user_id = r.business_id WHERE r.id = ? AND r.referrer_id = ? LIMIT 1');
    $statement->execute([$referralId, $referrerId]);
    return $statement->fetch() ?: null;
}

function business_referral(int $referralId, int $businessId): ?array
{
    $statement = db()->prepare('SELECT r.*, o.title AS opportunity_title, o.category, o.service_location, o.description AS opportunity_description, o.valid_until, u.full_name AS referrer_name, u.email AS referrer_email, u.phone AS referrer_phone, p.profile_photo AS referrer_photo FROM customer_referrals r JOIN referral_opportunities o ON o.id = r.opportunity_id JOIN users u ON u.id = r.referrer_id LEFT JOIN referrer_profiles p ON p.user_id = r.referrer_id WHERE r.id = ? AND r.business_id = ? LIMIT 1');
    $statement->execute([$referralId, $businessId]);
    return $statement->fetch() ?: null;
}

function business_referral_stats(int $businessId): array
{
    $statement = db()->prepare("SELECT COUNT(*) AS received, COALESCE(SUM(status IN ('Submitted', 'Under Review', 'Processing')), 0) AS pending, COALESCE(SUM(status = 'Accepted'), 0) AS accepted, COALESCE(SUM(status = 'Rejected'), 0) AS rejected, COALESCE(SUM(status = 'Completed'), 0) AS completed FROM customer_referrals WHERE business_id = ?");
    $statement->execute([$businessId]);
    $stats = $statement->fetch() ?: [];
    $statement = db()->prepare("SELECT COALESCE(SUM(wt.amount), 0) FROM wallet_transactions wt JOIN customer_referrals r ON r.id = wt.referral_id WHERE r.business_id = ? AND wt.transaction_type = 'Reward Credit'");
    $statement->execute([$businessId]);
    return [
        'received' => (int) ($stats['received'] ?? 0),
        'pending' => (int) ($stats['pending'] ?? 0),
        'accepted' => (int) ($stats['accepted'] ?? 0),
        'rejected' => (int) ($stats['rejected'] ?? 0),
        'completed' => (int) ($stats['completed'] ?? 0),
        'rewards_paid' => (float) $statement->fetchColumn(),
    ];
}

function generate_referral_code(int $referralId, string $submittedAt): string
{
    $date = date('Ymd', strtotime($submittedAt));
    return 'REF-' . $date . '-' . str_pad((string) $referralId, 6, '0', STR_PAD_LEFT);
}

function mask_phone(string $phone): string
{
    $digits = preg_replace('/[^0-9]/', '', $phone);
    $len = strlen($digits);
    if ($len <= 4) return str_repeat('*', $len);
    return substr($digits, 0, 2) . str_repeat('*', $len - 4) . substr($digits, -2);
}

function mask_email(string $email): string
{
    if ($email === '') return '';
    $parts = explode('@', $email);
    if (count($parts) !== 2) return '***@***.***';
    $local = $parts[0];
    $domain = $parts[1];
    $maskedLocal = substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 2));
    return $maskedLocal . '@' . $domain;
}

function is_contact_visible(array $referral): bool
{
    return in_array($referral['customer_approval_status'] ?? 'pending', ['approved'], true)
        || in_array($referral['status'], ['Customer Approved', 'Accepted', 'Completed'], true);
}

function generate_approval_token(int $referralId): string
{
    $token = bin2hex(random_bytes(32));
    $expiresAt = (new DateTimeImmutable('+7 days'))->format('Y-m-d H:i:s');
    db()->prepare('UPDATE customer_referrals SET customer_approval_token = ?, customer_approval_token_expires_at = ?, customer_approval_status = ? WHERE id = ?')
        ->execute([$token, $expiresAt, 'waiting', $referralId]);
    return $token;
}

function validate_approval_token(string $token): ?array
{
    if (strlen($token) !== 64) return null;
    $stmt = db()->prepare('SELECT r.*, o.title AS opportunity_title, COALESCE(b.business_name, u.full_name) AS business_name FROM customer_referrals r JOIN referral_opportunities o ON o.id = r.opportunity_id JOIN users u ON u.id = r.business_id LEFT JOIN business_profiles b ON b.user_id = r.business_id WHERE r.customer_approval_token = ? AND r.customer_approval_token_expires_at > NOW() AND r.customer_approval_status = ? LIMIT 1');
    $stmt->execute([$token, 'waiting']);
    return $stmt->fetch() ?: null;
}

function approve_referral_by_customer(string $token): bool
{
    $referral = validate_approval_token($token);
    if (!$referral) return false;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE customer_referrals SET status = 'Customer Approved', customer_approval_status = 'approved', customer_approval_timestamp = NOW(), customer_approval_token = NULL WHERE id = ? AND customer_approval_status = 'waiting'")
            ->execute([(int) $referral['id']]);
        add_referral_history((int) $referral['id'], 'Customer Approved', 'Customer approved sharing contact details.');
        $pdo->commit();

        // Notify business
        try {
            NotificationService::notifyUser((int) $referral['business_id'], 'Customer Approved Contact Sharing', 'Customer ' . $referral['customer_name'] . ' approved sharing their contact details for referral ' . $referral['referral_code'] . '.', 'Customer approved contact access', '<p>The customer has approved sharing their contact details.</p><p><strong>Referral:</strong> ' . e($referral['referral_code']) . '<br><strong>Customer:</strong> ' . e($referral['customer_name']) . '<br><strong>Campaign:</strong> ' . e($referral['opportunity_title']) . '</p><p>You can now view full contact details and proceed with this referral.</p>', absolute_url('business/referral_view.php?id=' . $referral['id']), 'SYSTEM', (int) $referral['id']);
        } catch (Throwable $e) { app_log('Customer approval notification failed: ' . $e->getMessage()); }

        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        app_log('Customer approval failed: ' . $e->getMessage());
        return false;
    }
}

function decline_referral_by_customer(string $token): bool
{
    $referral = validate_approval_token($token);
    if (!$referral) return false;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE customer_referrals SET status = 'Declined by Customer', customer_approval_status = 'declined', customer_approval_timestamp = NOW(), customer_approval_token = NULL WHERE id = ? AND customer_approval_status = 'waiting'")
            ->execute([(int) $referral['id']]);
        add_referral_history((int) $referral['id'], 'Declined by Customer', 'Customer declined sharing contact details.');
        $pdo->commit();

        // Notify business
        try {
            NotificationService::notifyUser((int) $referral['business_id'], 'Customer Declined Contact Sharing', 'Customer declined sharing contact details for referral ' . $referral['referral_code'] . '.', 'Customer declined contact access', '<p>The customer has declined sharing their contact details for this referral.</p><p><strong>Referral:</strong> ' . e($referral['referral_code']) . '<br><strong>Campaign:</strong> ' . e($referral['opportunity_title']) . '</p>', absolute_url('business/referral_view.php?id=' . $referral['id']), 'SYSTEM', (int) $referral['id']);
        } catch (Throwable $e) { app_log('Customer decline notification failed: ' . $e->getMessage()); }

        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        app_log('Customer decline failed: ' . $e->getMessage());
        return false;
    }
}
