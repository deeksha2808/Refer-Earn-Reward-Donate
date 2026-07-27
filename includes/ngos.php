<?php
declare(strict_types=1);

function get_ngos(): array
{
    try {
        $stmt = db()->query('SELECT id, name, category, description, city, district, state, email, phone, address, website, logo FROM ngos WHERE is_active = 1 ORDER BY name ASC');
        return $stmt->fetchAll();
    } catch (PDOException $exception) {
        app_log('NGO module is unavailable: ' . $exception->getMessage());
        return [];
    }
}

function get_ngos_filtered(string $search = '', string $district = '', string $category = ''): array
{
    $where = ['is_active = 1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(name LIKE ? OR description LIKE ? OR city LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    if ($district !== '') {
        $where[] = 'district = ?';
        $params[] = $district;
    }
    if ($category !== '') {
        $where[] = 'category = ?';
        $params[] = $category;
    }
    $sql = 'SELECT id, name, category, description, city, district, state, email, phone, address, website, logo FROM ngos WHERE ' . implode(' AND ', $where) . ' ORDER BY name ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_ngo(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM ngos WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function ngo_exists(int $id): bool
{
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM ngos WHERE id = ? AND is_active = 1');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException) {
        return false;
    }
}

function ngo_districts(): array
{
    $stmt = db()->query('SELECT DISTINCT district FROM ngos WHERE is_active = 1 AND district IS NOT NULL ORDER BY district');
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function ngo_categories(): array
{
    $stmt = db()->query('SELECT DISTINCT category FROM ngos WHERE is_active = 1 AND category IS NOT NULL ORDER BY category');
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function donation_reports_by_ngo(int $walletId): array
{
    $stmt = db()->prepare('SELECT n.name AS ngo_name, n.district, n.category, COUNT(d.id) AS total_donations, SUM(d.donation_amount) AS total_amount FROM donations d JOIN ngos n ON n.id = d.ngo_id WHERE d.wallet_id = ? AND d.ngo_id IS NOT NULL GROUP BY n.id, n.name, n.district, n.category ORDER BY total_amount DESC');
    $stmt->execute([$walletId]);
    return $stmt->fetchAll();
}

function donation_reports_by_district(int $walletId): array
{
    $stmt = db()->prepare('SELECT n.district, COUNT(d.id) AS total_donations, SUM(d.donation_amount) AS total_amount FROM donations d JOIN ngos n ON n.id = d.ngo_id WHERE d.wallet_id = ? AND d.ngo_id IS NOT NULL GROUP BY n.district ORDER BY total_amount DESC');
    $stmt->execute([$walletId]);
    return $stmt->fetchAll();
}

function donation_reports_by_category(int $walletId): array
{
    $stmt = db()->prepare('SELECT n.category, COUNT(d.id) AS total_donations, SUM(d.donation_amount) AS total_amount FROM donations d JOIN ngos n ON n.id = d.ngo_id WHERE d.wallet_id = ? AND d.ngo_id IS NOT NULL GROUP BY n.category ORDER BY total_amount DESC');
    $stmt->execute([$walletId]);
    return $stmt->fetchAll();
}

function donation_analytics(int $walletId): array
{
    $pdo = db();
    $byNgo = $pdo->prepare("SELECT n.name AS label, SUM(d.donation_amount) AS value FROM donations d JOIN ngos n ON n.id = d.ngo_id WHERE d.wallet_id = ? AND d.ngo_id IS NOT NULL GROUP BY n.id, n.name ORDER BY value DESC LIMIT 10");
    $byNgo->execute([$walletId]);

    $byDistrict = $pdo->prepare("SELECT n.district AS label, SUM(d.donation_amount) AS value FROM donations d JOIN ngos n ON n.id = d.ngo_id WHERE d.wallet_id = ? AND d.ngo_id IS NOT NULL GROUP BY n.district ORDER BY value DESC");
    $byDistrict->execute([$walletId]);

    $byCategory = $pdo->prepare("SELECT n.category AS label, SUM(d.donation_amount) AS value FROM donations d JOIN ngos n ON n.id = d.ngo_id WHERE d.wallet_id = ? AND d.ngo_id IS NOT NULL GROUP BY n.category ORDER BY value DESC");
    $byCategory->execute([$walletId]);

    $monthly = $pdo->prepare("SELECT DATE_FORMAT(d.created_at, '%b %Y') AS label, DATE_FORMAT(d.created_at, '%Y-%m') AS ordering, SUM(d.donation_amount) AS value FROM donations d WHERE d.wallet_id = ? GROUP BY ordering, label ORDER BY ordering");
    $monthly->execute([$walletId]);

    return [
        'by_ngo' => $byNgo->fetchAll(),
        'by_district' => $byDistrict->fetchAll(),
        'by_category' => $byCategory->fetchAll(),
        'monthly' => $monthly->fetchAll(),
    ];
}

function register_ngo(array $data): int
{
    $stmt = db()->prepare('INSERT INTO ngos (name, category, description, city, district, state, email, phone, address, website, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())');
    $stmt->execute([$data['name'], $data['category'] ?? null, $data['description'] ?? null, $data['city'] ?? null, $data['district'] ?? null, $data['state'] ?? 'Karnataka', $data['email'] ?: null, $data['phone'] ?: null, $data['address'] ?: null, $data['website'] ?: null]);
    return (int) db()->lastInsertId();
}
