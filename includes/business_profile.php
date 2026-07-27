<?php
declare(strict_types=1);

const BUSINESS_PROFILE_CATEGORIES = ['Construction', 'Architecture', 'Interior Design', 'Automobile', 'Real Estate', 'Education', 'Healthcare', 'Finance', 'Insurance', 'Technology', 'Legal', 'Other'];

function business_profile(int $userId): ?array
{
    $statement = db()->prepare('SELECT * FROM business_profiles WHERE user_id = ? LIMIT 1');
    $statement->execute([$userId]);
    return $statement->fetch() ?: null;
}

function business_profile_is_complete(int $userId): bool
{
    $profile = business_profile($userId);
    return $profile !== null && (int) $profile['is_profile_completed'] === 1;
}

function business_profile_completion(array $profile): int
{
    $fields = ['business_name', 'owner_name', 'business_email', 'business_phone', 'business_category', 'business_address', 'city', 'state', 'country', 'pincode', 'business_description', 'logo', 'verification_document'];
    $completed = count(array_filter($fields, static fn (string $field): bool => trim((string) ($profile[$field] ?? '')) !== ''));
    return (int) round(($completed / count($fields)) * 100);
}

function business_upload(array $upload, string $type): ?string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) throw new RuntimeException('The uploaded file could not be processed. Please try again.');
    $limits = ['logo' => 2 * 1024 * 1024, 'document' => 5 * 1024 * 1024];
    if (($upload['size'] ?? 0) > $limits[$type]) throw new RuntimeException($type === 'logo' ? 'Logo files must be 2 MB or smaller.' : 'Verification documents must be 5 MB or smaller.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $allowed = $type === 'logo'
        ? ['image/png' => 'png', 'image/jpeg' => 'jpg']
        : ['image/png' => 'png', 'image/jpeg' => 'jpg', 'application/pdf' => 'pdf'];
    if (!isset($allowed[$mime])) throw new RuntimeException($type === 'logo' ? 'Upload a PNG, JPG, or JPEG logo.' : 'Upload a PNG, JPG, JPEG, or PDF document.');

    $directory = dirname(__DIR__) . '/uploads/' . ($type === 'logo' ? 'business_logos' : 'business_documents');
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('Upload storage could not be created.');
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($upload['tmp_name'], $directory . '/' . $filename)) throw new RuntimeException('The uploaded file could not be saved.');
    return 'uploads/' . ($type === 'logo' ? 'business_logos/' : 'business_documents/') . $filename;
}
