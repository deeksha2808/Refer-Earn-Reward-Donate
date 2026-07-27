<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/business_profile.php';
require_once __DIR__ . '/../includes/customer_referrals.php';
require_once __DIR__ . '/../includes/reports.php';
$user = require_login('BUSINESS');
if (!business_profile_is_complete((int) $user['id'])) { http_response_code(403); exit('Complete your business profile to export reports.'); }
$type = in_array($_GET['type'] ?? '', BUSINESS_REPORT_TYPES, true) ? (string) $_GET['type'] : 'referrals';
$format = in_array($_GET['format'] ?? '', ['csv', 'xlsx', 'pdf'], true) ? (string) $_GET['format'] : 'csv';
$filters = report_filters($_GET); $columns = report_export_columns($type); $rows = report_export_rows((int) $user['id'], $filters, $type); $filename = 'business-' . rtrim($type, 's') . '-report-' . date('Ymd-His');
ActivityLogService::logActivity((int) $user['id'], 'BUSINESS', 'Reports', strtoupper($format) . ' Export', 'Report', null, ucfirst($type) . ' report exported as ' . strtoupper($format) . '.');
if ($format === 'xlsx') export_report_xlsx($columns, $rows, $filename);
if ($format === 'pdf') export_report_pdf($columns, $rows, ucfirst($type) . ' Report', $filename);
export_report_csv($columns, $rows, $filename);
