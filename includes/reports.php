<?php
declare(strict_types=1);

const BUSINESS_REPORT_TYPES = ['referrals', 'commissions', 'campaigns', 'earnings'];

function report_filters(array $input): array
{
    $date = static function (string $value): string {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $parsed && $parsed->format('Y-m-d') === $value ? $value : '';
    };
    return [
        'date_from' => $date(trim((string) ($input['date_from'] ?? ''))),
        'date_to' => $date(trim((string) ($input['date_to'] ?? ''))),
        'campaign_id' => filter_var($input['campaign_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
        'product' => trim((string) ($input['product'] ?? '')),
        'status' => in_array($input['status'] ?? '', CUSTOMER_REFERRAL_STATUSES, true) ? (string) $input['status'] : '',
        'referrer_id' => filter_var($input['referrer_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
        'search' => mb_substr(trim((string) ($input['search'] ?? '')), 0, 100),
    ];
}

/** Returns a parameterized referral WHERE clause shared by every report and export. */
function report_referral_where(int $businessId, array $filters, string $alias = 'r'): array
{
    $where = ["{$alias}.business_id = ?"]; $params = [$businessId];
    if ($filters['date_from'] !== '') { $where[] = "{$alias}.submitted_at >= ?"; $params[] = $filters['date_from'] . ' 00:00:00'; }
    if ($filters['date_to'] !== '') { $where[] = "{$alias}.submitted_at < DATE_ADD(?, INTERVAL 1 DAY)"; $params[] = $filters['date_to']; }
    if ($filters['campaign_id']) { $where[] = "{$alias}.opportunity_id = ?"; $params[] = $filters['campaign_id']; }
    if ($filters['product'] !== '') { $where[] = "{$alias}.product_name = ?"; $params[] = $filters['product']; }
    if ($filters['status'] !== '') { $where[] = "{$alias}.status = ?"; $params[] = $filters['status']; }
    if ($filters['referrer_id']) { $where[] = "{$alias}.referrer_id = ?"; $params[] = $filters['referrer_id']; }
    if ($filters['search'] !== '') { $where[] = "({$alias}.customer_name LIKE ? OR {$alias}.product_name LIKE ? OR o.title LIKE ? OR u.full_name LIKE ?)"; foreach (range(1, 4) as $_) $params[] = '%' . $filters['search'] . '%'; }
    return [implode(' AND ', $where), $params];
}

function report_options(int $businessId): array
{
    $pdo = db();
    $campaigns = $pdo->prepare('SELECT id, title FROM referral_opportunities WHERE business_id = ? ORDER BY title'); $campaigns->execute([$businessId]);
    $products = $pdo->prepare("SELECT DISTINCT r.product_name FROM customer_referrals r WHERE r.business_id = ? AND r.product_name IS NOT NULL AND r.product_name <> '' ORDER BY r.product_name"); $products->execute([$businessId]);
    $referrers = $pdo->prepare('SELECT DISTINCT u.id, u.full_name FROM customer_referrals r JOIN users u ON u.id = r.referrer_id WHERE r.business_id = ? ORDER BY u.full_name'); $referrers->execute([$businessId]);
    return ['campaigns' => $campaigns->fetchAll(), 'products' => $products->fetchAll(PDO::FETCH_COLUMN), 'referrers' => $referrers->fetchAll()];
}

function report_referrals(int $businessId, array $filters, string $sort = 'submitted_at', string $direction = 'desc', ?int $limit = null, int $offset = 0): array
{
    [$where, $params] = report_referral_where($businessId, $filters);
    $columns = ['id' => 'r.id', 'customer_name' => 'r.customer_name', 'product' => 'r.product_name', 'campaign' => 'o.title', 'status' => 'r.status', 'referrer' => 'u.full_name', 'sale_amount' => 'r.sale_amount', 'commission' => 'r.calculated_commission', 'submitted_at' => 'r.submitted_at', 'completed_at' => 'r.completed_at'];
    $order = $columns[$sort] ?? $columns['submitted_at']; $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';
    $sql = "SELECT r.id, r.customer_name, r.product_name, o.title AS campaign_name, r.status, u.full_name AS referrer_name, r.sale_amount, r.commission_percentage, r.calculated_commission, r.platform_fee, r.net_commission, r.submitted_at, r.completed_at FROM customer_referrals r JOIN referral_opportunities o ON o.id = r.opportunity_id JOIN users u ON u.id = r.referrer_id WHERE {$where} ORDER BY {$order} {$direction}, r.id DESC";
    if ($limit !== null) { $sql .= ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset); }
    $statement = db()->prepare($sql); $statement->execute($params); return $statement->fetchAll();
}

function report_referral_count(int $businessId, array $filters): int
{
    [$where, $params] = report_referral_where($businessId, $filters);
    $statement = db()->prepare("SELECT COUNT(*) FROM customer_referrals r JOIN referral_opportunities o ON o.id = r.opportunity_id JOIN users u ON u.id = r.referrer_id WHERE {$where}"); $statement->execute($params); return (int) $statement->fetchColumn();
}

function report_commissions(int $businessId, array $filters): array
{
    [$where, $params] = report_referral_where($businessId, $filters);
    $statement = db()->prepare("SELECT u.full_name AS referrer_name, r.product_name, o.title AS campaign_name, r.sale_amount, r.commission_percentage, r.calculated_commission, r.platform_fee, r.net_commission, r.completed_at FROM customer_referrals r JOIN referral_opportunities o ON o.id = r.opportunity_id JOIN users u ON u.id = r.referrer_id WHERE {$where} AND r.status = 'Completed' ORDER BY r.completed_at DESC, r.id DESC");
    $statement->execute($params); return $statement->fetchAll();
}

function report_campaign_performance(int $businessId, array $filters): array
{
    [$where, $params] = report_referral_where($businessId, $filters);
    $statement = db()->prepare("SELECT o.title AS campaign_name, COUNT(r.id) AS total_referrals, COALESCE(SUM(r.status = 'Accepted'),0) AS accepted_referrals, COALESCE(SUM(r.status = 'Rejected'),0) AS rejected_referrals, COALESCE(SUM(r.status = 'Completed'),0) AS completed_referrals, COALESCE(SUM(r.sale_amount),0) AS total_revenue, COALESCE(SUM(r.calculated_commission),0) AS total_commission_paid, COALESCE(SUM(r.platform_fee),0) AS total_platform_fees, COALESCE(SUM(r.net_commission),0) AS total_net_commission FROM customer_referrals r JOIN referral_opportunities o ON o.id = r.opportunity_id JOIN users u ON u.id = r.referrer_id WHERE {$where} GROUP BY o.id, o.title ORDER BY total_revenue DESC, campaign_name ASC");
    $statement->execute($params); $rows = $statement->fetchAll();
    foreach ($rows as &$row) $row['conversion_rate'] = (int) $row['total_referrals'] ? round((int) $row['completed_referrals'] * 100 / (int) $row['total_referrals'], 2) : 0.0;
    return $rows;
}

function report_earnings_summary(int $businessId, array $filters): array
{
    [$where, $params] = report_referral_where($businessId, $filters);
    $statement = db()->prepare("SELECT COALESCE(SUM(r.sale_amount),0) AS total_revenue, COALESCE(SUM(r.calculated_commission),0) AS total_commission_paid, COALESCE(SUM(r.platform_fee),0) AS total_platform_fees, COALESCE(SUM(r.net_commission),0) AS total_net_commission, COALESCE(SUM(r.status = 'Completed'),0) AS completed_referrals, COALESCE(SUM(r.status IN ('Submitted','Under Review','Processing','Accepted')),0) AS pending_referrals, COUNT(DISTINCT CASE WHEN o.status = 'Active' THEN o.id END) AS active_campaigns FROM customer_referrals r JOIN referral_opportunities o ON o.id = r.opportunity_id JOIN users u ON u.id = r.referrer_id WHERE {$where}");
    $statement->execute($params); return $statement->fetch() ?: [];
}

function report_chart_data(int $businessId, array $filters): array
{
    [$where, $params] = report_referral_where($businessId, $filters);
    $pdo = db();
    $queries = [
        'monthly_referrals' => "SELECT DATE_FORMAT(r.submitted_at, '%b %Y') AS label, DATE_FORMAT(r.submitted_at, '%Y-%m') AS ordering, COUNT(*) AS value FROM customer_referrals r JOIN referral_opportunities o ON o.id=r.opportunity_id JOIN users u ON u.id=r.referrer_id WHERE {$where} GROUP BY ordering, label ORDER BY ordering",
        'monthly_commission' => "SELECT DATE_FORMAT(r.completed_at, '%b %Y') AS label, DATE_FORMAT(r.completed_at, '%Y-%m') AS ordering, COALESCE(SUM(r.calculated_commission),0) AS value FROM customer_referrals r JOIN referral_opportunities o ON o.id=r.opportunity_id JOIN users u ON u.id=r.referrer_id WHERE {$where} AND r.status='Completed' GROUP BY ordering, label ORDER BY ordering",
        'status_distribution' => "SELECT r.status AS label, COUNT(*) AS value FROM customer_referrals r JOIN referral_opportunities o ON o.id=r.opportunity_id JOIN users u ON u.id=r.referrer_id WHERE {$where} GROUP BY r.status ORDER BY r.status",
        'campaign_performance' => "SELECT o.title AS label, COUNT(*) AS value FROM customer_referrals r JOIN referral_opportunities o ON o.id=r.opportunity_id JOIN users u ON u.id=r.referrer_id WHERE {$where} GROUP BY o.id, o.title ORDER BY value DESC, label LIMIT 8",
    ];
    $result = []; foreach ($queries as $key => $sql) { $stmt = $pdo->prepare($sql); $stmt->execute($params); $result[$key] = $stmt->fetchAll(); } return $result;
}

function report_export_columns(string $type): array
{
    return match ($type) {
        'commissions' => ['Referrer Name' => 'referrer_name', 'Product' => 'product_name', 'Campaign' => 'campaign_name', 'Sale Amount' => 'sale_amount', 'Commission %' => 'commission_percentage', 'Gross Commission' => 'calculated_commission', 'Platform Fee (2%)' => 'platform_fee', 'Net Commission' => 'net_commission', 'Completion Date' => 'completed_at'],
        'campaigns' => ['Campaign Name' => 'campaign_name', 'Total Referrals' => 'total_referrals', 'Accepted Referrals' => 'accepted_referrals', 'Rejected Referrals' => 'rejected_referrals', 'Completed Referrals' => 'completed_referrals', 'Conversion Rate' => 'conversion_rate', 'Total Revenue' => 'total_revenue', 'Gross Commission' => 'total_commission_paid', 'Platform Fees' => 'total_platform_fees', 'Net Commission Paid' => 'total_net_commission'],
        'earnings' => ['Metric' => 'metric', 'Value' => 'value'],
        default => ['Referral ID' => 'id', 'Customer Name' => 'customer_name', 'Product' => 'product_name', 'Campaign' => 'campaign_name', 'Referral Status' => 'status', 'Referrer Name' => 'referrer_name', 'Sale Amount' => 'sale_amount', 'Commission %' => 'commission_percentage', 'Gross Commission' => 'calculated_commission', 'Platform Fee' => 'platform_fee', 'Net Commission' => 'net_commission', 'Date Submitted' => 'submitted_at', 'Date Completed' => 'completed_at'],
    };
}

function report_export_rows(int $businessId, array $filters, string $type): array
{
    if ($type === 'commissions') return report_commissions($businessId, $filters);
    if ($type === 'campaigns') return report_campaign_performance($businessId, $filters);
    if ($type === 'earnings') { $summary = report_earnings_summary($businessId, $filters); $rows = []; foreach (['Total Revenue' => 'total_revenue', 'Total Gross Commission' => 'total_commission_paid', 'Total Platform Fees (2%)' => 'total_platform_fees', 'Total Net Commission Paid' => 'total_net_commission', 'Active Campaigns' => 'active_campaigns', 'Completed Referrals' => 'completed_referrals', 'Pending Referrals' => 'pending_referrals'] as $label => $field) $rows[] = ['metric' => $label, 'value' => $summary[$field] ?? 0]; return $rows; }
    return report_referrals($businessId, $filters);
}

function report_cell(mixed $value): string
{
    $value = $value === null ? '' : (string) $value;
    // Prevent user-supplied spreadsheet formulas from executing when a CSV/XLSX is opened.
    return preg_match('/^[=+@-]/', $value) ? "'" . $value : $value;
}

function export_report_csv(array $columns, array $rows, string $filename): void
{
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $out = fopen('php://output', 'wb'); fputcsv($out, array_keys($columns)); foreach ($rows as $row) fputcsv($out, array_map(static fn($field) => report_cell($row[$field] ?? ''), array_values($columns))); fclose($out); exit;
}

function export_report_xlsx(array $columns, array $rows, string $filename): void
{
    if (!class_exists('ZipArchive')) { throw new RuntimeException('Excel export requires the PHP ZIP extension.'); }
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $sheetRows = []; $allRows = [array_keys($columns)]; foreach ($rows as $row) $allRows[] = array_map(static fn($field) => report_cell($row[$field] ?? ''), array_values($columns));
    foreach ($allRows as $r => $values) { $cells = []; foreach ($values as $c => $value) { $ref = chr(65 + $c) . ($r + 1); $cells[] = '<c r="' . $ref . '" t="inlineStr"><is><t>' . $escape($value) . '</t></is></c>'; } $sheetRows[] = '<row r="' . ($r + 1) . '">' . implode('', $cells) . '</row>'; }
    $tmp = tempnam(sys_get_temp_dir(), 'report-'); $zip = new ZipArchive(); $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . implode('', $sheetRows) . '</sheetData></worksheet>'); $zip->close();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"'); header('Content-Length: ' . filesize($tmp)); readfile($tmp); unlink($tmp); exit;
}

function export_report_pdf(array $columns, array $rows, string $title, string $filename): void
{
    $lines = [$title, 'Generated: ' . date('d M Y H:i')]; $lines[] = implode(' | ', array_keys($columns)); foreach ($rows as $row) $lines[] = implode(' | ', array_map(static fn($field) => mb_substr(report_cell($row[$field] ?? ''), 0, 35), array_values($columns)));
    $pages = array_chunk($lines, 42); $objects = []; $objects[] = '<< /Type /Catalog /Pages 2 0 R >>'; $kids = []; $pageStart = 3;
    foreach ($pages as $index => $page) { $pageId = $pageStart + $index * 2; $kids[] = $pageId . ' 0 R'; }
    $objects[] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($pages) . ' >>';
    foreach ($pages as $index => $page) { $pageId = $pageStart + $index * 2; $contentId = $pageId + 1; $content = "BT /F1 8 Tf 40 800 Td 12 TL "; foreach ($page as $line) $content .= '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], mb_convert_encoding($line, 'ISO-8859-1', 'UTF-8')) . ') Tj T* '; $content .= 'ET'; $objects[] = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 ' . ($pageStart + count($pages) * 2) . ' 0 R >> >> /MediaBox [0 0 842 842] /Contents ' . $contentId . ' 0 R >>'; $objects[] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream"; }
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'; $pdf = "%PDF-1.4\n"; $offsets = [0]; foreach ($objects as $i => $object) { $offsets[] = strlen($pdf); $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n"; } $xref = strlen($pdf); $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n"; foreach (array_slice($offsets, 1) as $offset) $pdf .= sprintf('%010d 00000 n ', $offset) . "\n"; $pdf .= 'trailer << /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>' . "\nstartxref\n{$xref}\n%%EOF";
    header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="' . $filename . '.pdf"'); echo $pdf; exit;
}
