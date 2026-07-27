<?php
declare(strict_types=1);

const REFERRAL_OPPORTUNITY_CATEGORIES = ['Construction', 'Electrical', 'Interior Design', 'Automobile', 'Education', 'Healthcare', 'Finance', 'Technology', 'Legal'];
const REFERRAL_OPPORTUNITY_STATUSES = ['Active', 'Inactive'];

function opportunity_defaults(): array
{
    return ['title' => '', 'category' => '', 'description' => '', 'service_location' => '', 'valid_until' => '', 'status' => 'Active', 'products' => []];
}

function business_opportunity(int $opportunityId, int $businessId): ?array
{
    $statement = db()->prepare('SELECT * FROM referral_opportunities WHERE id = ? AND business_id = ? LIMIT 1');
    $statement->execute([$opportunityId, $businessId]);
    return $statement->fetch() ?: null;
}

function opportunity_products(int $opportunityId): array
{
    $statement = db()->prepare('SELECT id, product_name, commission_percentage FROM opportunity_products WHERE opportunity_id = ? ORDER BY id');
    $statement->execute([$opportunityId]);
    return $statement->fetchAll();
}

function business_opportunity_stats(int $businessId): array
{
    $statement = db()->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(status = 'Active'), 0) AS active, COALESCE(SUM(status = 'Inactive'), 0) AS inactive FROM referral_opportunities WHERE business_id = ?");
    $statement->execute([$businessId]); $stats = $statement->fetch() ?: [];
    return ['total' => (int) ($stats['total'] ?? 0), 'active' => (int) ($stats['active'] ?? 0), 'inactive' => (int) ($stats['inactive'] ?? 0)];
}

function parse_display_date(string $value): ?string
{
    $value = trim($value);
    foreach (['!d/m/Y' => 'd/m/Y', '!Y-m-d' => 'Y-m-d'] as $format => $expected) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date && $date->format($expected) === $value) return $date->format('Y-m-d');
    }
    return null;
}

function format_display_date(?string $value): string
{
    $date = $value ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
    return $date ? $date->format('d/m/Y') : '';
}

function validate_opportunity(array $input): array
{
    $values = opportunity_defaults();
    foreach (['title', 'category', 'description', 'service_location', 'valid_until', 'status'] as $field) $values[$field] = trim((string) ($input[$field] ?? ''));
    $errors = [];
    if (mb_strlen($values['title']) < 3 || mb_strlen($values['title']) > 150) $errors['title'] = 'Enter a title between 3 and 150 characters.';
    if ($values['category'] === '' || mb_strlen($values['category']) > 100) $errors['category'] = 'Enter a main category of up to 100 characters.';
    if (mb_strlen($values['description']) > 5000) $errors['description'] = 'Keep the description to 5,000 characters or fewer.';
    if ($values['service_location'] === '' || mb_strlen($values['service_location']) > 150) $errors['service_location'] = 'Enter a service location of up to 150 characters.';
    $date = parse_display_date($values['valid_until']);
    if (!$date || $date < (new DateTimeImmutable('today'))->format('Y-m-d')) $errors['valid_until'] = 'Valid until must be today or a future date.'; else $values['valid_until'] = $date;
    if (!in_array($values['status'], REFERRAL_OPPORTUNITY_STATUSES, true)) $errors['status'] = 'Choose a valid status.';
    $names = (array) ($input['product_name'] ?? []); $rates = (array) ($input['commission_percentage'] ?? []); $products = [];
    foreach ($names as $index => $name) {
        $name = trim((string) $name); $rate = trim((string) ($rates[$index] ?? ''));
        if ($name === '' && $rate === '') continue;
        if ($name === '' || mb_strlen($name) > 150 || !is_numeric($rate) || (float) $rate <= 0 || (float) $rate > 100) { $errors['products'] = 'Each product needs a name and a commission between 0.01% and 100%.'; break; }
        $products[] = ['name' => $name, 'rate' => number_format((float) $rate, 2, '.', '')];
    }
    if (!$products) $errors['products'] = 'Add at least one product or service with a commission percentage.';
    $seenNames = [];
    foreach ($products as $product) {
        $key = mb_strtolower(trim($product['name']));
        if (isset($seenNames[$key])) {
            $errors['products'] = 'Each product or service can be added only once to a campaign.';
            break;
        }
        $seenNames[$key] = true;
    }
    $values['products'] = $products;
    return [$values, $errors];
}

function opportunity_money(string|float|int|null $amount): string { return '₹' . number_format((float) $amount, 2); }
