<?php
declare(strict_types=1);

const REFERRER_SERVICE_CATEGORIES = ['Construction', 'Architecture', 'Interior Design', 'Civil Contractor', 'Electrical', 'Plumbing', 'Painting', 'Carpentry', 'Automobile', 'Real Estate', 'Education', 'Healthcare', 'Finance', 'Insurance', 'Technology', 'Legal', 'Other'];
const REFERRER_GENDERS = ['Male', 'Female', 'Non-binary', 'Prefer not to say'];
const REFERRER_EXPERIENCE_LEVELS = ['Fresher', '1–3 Years', '3–5 Years', '5+ Years'];
const REFERRER_ID_TYPES = ['Aadhaar', 'PAN', 'Driving Licence', 'Passport', 'Voter ID'];

function referrer_profile(int $userId): ?array
{
    $statement = db()->prepare('SELECT * FROM referrer_profiles WHERE user_id = ? LIMIT 1');
    $statement->execute([$userId]);
    return $statement->fetch() ?: null;
}

function referrer_profile_is_complete(int $userId): bool
{
    $profile = referrer_profile($userId);
    return $profile !== null && (int) $profile['is_profile_completed'] === 1;
}

function referrer_format_date_for_input(string $date): string
{
    $date = trim($date);
    if ($date === '') return '';
    $fromDatabase = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if ($fromDatabase && $fromDatabase->format('Y-m-d') === $date) return $fromDatabase->format('d/m/Y');
    $fromInput = DateTimeImmutable::createFromFormat('!d/m/Y', $date);
    if ($fromInput && $fromInput->format('d/m/Y') === $date) return $date;
    return $date;
}

function referrer_parse_date(string $date): ?string
{
    $date = trim($date);
    if ($date === '') return null;
    $fromInput = DateTimeImmutable::createFromFormat('!d/m/Y', $date);
    if ($fromInput && $fromInput->format('d/m/Y') === $date) return $fromInput->format('Y-m-d');
    $fromDatabase = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if ($fromDatabase && $fromDatabase->format('Y-m-d') === $date) return $date;
    return null;
}

function referrer_profile_completion(array $profile): int
{
    $fields = ['full_name', 'date_of_birth', 'gender', 'mobile_number', 'email', 'profile_photo', 'address', 'city', 'state', 'country', 'pincode', 'occupation', 'service_categories', 'experience_level', 'bio', 'government_id_type', 'government_id_number', 'government_id_document', 'bank_account_name', 'bank_account_number', 'ifsc_code'];
    $completed = count(array_filter($fields, static fn(string $field): bool => trim((string) ($profile[$field] ?? '')) !== '' && ($field !== 'service_categories' || ($profile[$field] ?? '[]') !== '[]')));
    return (int) round(($completed / count($fields)) * 100);
}

function referrer_upload(array $upload, string $type): ?string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) throw new RuntimeException('The uploaded file could not be processed. Please try again.');
    $isPhoto = $type === 'photo';
    if (($upload['size'] ?? 0) > ($isPhoto ? 2 : 5) * 1024 * 1024) throw new RuntimeException($isPhoto ? 'Profile photos must be 2 MB or smaller.' : 'Verification documents must be 5 MB or smaller.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $allowed = $isPhoto ? ['image/png' => 'png', 'image/jpeg' => 'jpg'] : ['image/png' => 'png', 'image/jpeg' => 'jpg', 'application/pdf' => 'pdf'];
    if (!isset($allowed[$mime])) throw new RuntimeException($isPhoto ? 'Upload a PNG, JPG, or JPEG profile photo.' : 'Upload a PNG, JPG, JPEG, or PDF verification document.');
    $folder = $isPhoto ? 'profile_photos' : 'referrer_documents';
    $directory = dirname(__DIR__) . '/uploads/' . $folder;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('Upload storage could not be created.');
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($upload['tmp_name'], $directory . '/' . $filename)) throw new RuntimeException('The uploaded file could not be saved.');
    return 'uploads/' . $folder . '/' . $filename;
}
