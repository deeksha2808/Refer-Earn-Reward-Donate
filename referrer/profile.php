<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/business_profile.php';
require_once __DIR__ . '/../includes/location.php';
require_once __DIR__ . '/../includes/referrer_profile.php';

$user = require_login('REFERRER');
$existing = referrer_profile((int) $user['id']);
$fields = ['full_name' => $user['full_name'], 'date_of_birth' => '', 'gender' => '', 'mobile_number' => $user['phone'], 'alternate_mobile' => '', 'email' => $user['email'], 'address' => '', 'city' => '', 'state' => '', 'country' => 'India', 'pincode' => '', 'occupation' => '', 'experience_level' => '', 'bio' => '', 'government_id_type' => '', 'government_id_number' => '', 'bank_account_name' => '', 'bank_account_number' => '', 'ifsc_code' => '', 'upi_id' => ''];
$values = array_merge($fields, $existing ?: []);
if (isset($values['date_of_birth']) && $values['date_of_birth'] !== '') {
    $values['date_of_birth'] = referrer_format_date_for_input((string) $values['date_of_birth']);
}
$storedCategories = $existing ? (json_decode((string) $existing['service_categories'], true) ?: []) : [];
$customServiceCategory = '';
$selectedCategories = [];
foreach ($storedCategories as $category) {
    if (in_array($category, REFERRER_SERVICE_CATEGORIES, true)) $selectedCategories[] = $category;
    elseif ($customServiceCategory === '') $customServiceCategory = (string) $category;
}
if ($customServiceCategory !== '') $selectedCategories[] = 'Other';
$values['service_category_other'] = $customServiceCategory;
$values['city_other'] = '';
$errors = [];
$expectsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

/** @param array<string, string> $validationErrors */
function referrer_validation_response(array $validationErrors, bool $expectsJson): never
{
    app_log('Referrer profile validation failed: ' . json_encode($validationErrors, JSON_UNESCAPED_SLASHES));
    if ($expectsJson) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => $validationErrors], JSON_THROW_ON_ERROR);
        exit;
    }

    throw new RuntimeException('Validation failed.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        foreach ($fields as $field => $default) {
            $values[$field] = trim((string) ($_POST[$field] ?? $default));
        }
        $values['service_category_other'] = trim((string) ($_POST['service_category_other'] ?? ''));
        if (isset($values['date_of_birth']) && $values['date_of_birth'] !== '') {
            $parsedDob = referrer_parse_date((string) $values['date_of_birth']);
            if ($parsedDob !== null) {
                $values['date_of_birth'] = $parsedDob;
            }
        }
        $selectedCategories = array_values(array_unique(array_filter(
            (array) ($_POST['service_categories'] ?? []),
            static fn($category): bool => is_string($category) && in_array($category, REFERRER_SERVICE_CATEGORIES, true)
        )));
        if (in_array('Other', $selectedCategories, true)) {
            if ($values['service_category_other'] === '' || mb_strlen($values['service_category_other']) > 100) $errors['service_categories'] = 'Please specify a service category of up to 100 characters.';
            else $selectedCategories = array_values(array_diff($selectedCategories, ['Other'])); $selectedCategories[] = $values['service_category_other'];
        }

        if (mb_strlen($values['full_name']) < 2 || mb_strlen($values['full_name']) > 100) $errors['full_name'] = 'Full name must be between 2 and 100 characters.';
        $dobValue = $values['date_of_birth'] !== '' ? referrer_parse_date((string) $values['date_of_birth']) : null;
        if ($dobValue === null) {
            $errors['date_of_birth'] = 'Date of birth must be a valid date in the past.';
        } else {
            $values['date_of_birth'] = $dobValue;
            $dob = DateTime::createFromFormat('Y-m-d', $dobValue);
            if (!$dob || $dob->format('Y-m-d') !== $dobValue || $dob > new DateTime('today')) $errors['date_of_birth'] = 'Date of birth must be a valid date in the past.';
        }
        if (!in_array($values['gender'], REFERRER_GENDERS, true)) $errors['gender'] = 'Please choose a gender.';
        if ($values['mobile_number'] === '') $errors['mobile_number'] = 'Mobile number is required.';
        elseif (!preg_match('/^[0-9+() .-]{7,25}$/', $values['mobile_number'])) $errors['mobile_number'] = 'Mobile number format is invalid.';
        if ($values['alternate_mobile'] !== '' && !preg_match('/^[0-9+() .-]{7,25}$/', $values['alternate_mobile'])) $errors['alternate_mobile'] = 'Alternate mobile number format is invalid.';
        if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 150) $errors['email'] = 'Email address is invalid.';
        foreach (['address' => 255, 'city' => 100, 'state' => 100, 'country' => 100, 'pincode' => 20, 'occupation' => 150] as $field => $limit) {
            if ($values[$field] === '') $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            elseif (mb_strlen($values[$field]) > $limit) $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must be {$limit} characters or fewer.";
        }
        if (!$selectedCategories) $errors['service_categories'] = 'Choose at least one service category.';
        if (!in_array($values['experience_level'], REFERRER_EXPERIENCE_LEVELS, true)) $errors['experience_level'] = 'Please choose an experience level.';
        if (mb_strlen($values['bio']) < 20 || mb_strlen($values['bio']) > 3000) $errors['bio'] = 'Professional bio must be between 20 and 3,000 characters.';
        if (!in_array($values['government_id_type'], REFERRER_ID_TYPES, true)) $errors['government_id_type'] = 'Please choose a government ID type.';
        if ($values['government_id_number'] === '') $errors['government_id_number'] = 'Government ID Number is required.';
        elseif (!preg_match('/^[A-Za-z0-9 -]{4,100}$/', $values['government_id_number'])) $errors['government_id_number'] = 'Government ID Number format is invalid.';
        if (mb_strlen($values['bank_account_name']) < 2 || mb_strlen($values['bank_account_name']) > 150) $errors['bank_account_name'] = 'Bank account holder name must be between 2 and 150 characters.';
        if (!preg_match('/^[0-9]{9,34}$/', $values['bank_account_number'])) $errors['bank_account_number'] = 'Bank account number must contain 9 to 34 digits.';
        $values['ifsc_code'] = strtoupper($values['ifsc_code']);
        if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $values['ifsc_code'])) $errors['ifsc_code'] = 'IFSC Code must be 11 characters in a valid format.';
        if ($values['upi_id'] !== '' && !preg_match('/^[a-zA-Z0-9._-]{2,100}@[a-zA-Z]{2,50}$/', $values['upi_id'])) $errors['upi_id'] = 'UPI ID format is invalid.';

        if ($errors) referrer_validation_response($errors, $expectsJson);

        try { $photo = referrer_upload($_FILES['profile_photo'] ?? [], 'photo') ?: ($existing['profile_photo'] ?? null); }
        catch (Throwable $exception) { $errors['profile_photo'] = $exception->getMessage(); $photo = null; }
        try { $document = referrer_upload($_FILES['government_id_document'] ?? [], 'document') ?: ($existing['government_id_document'] ?? null); }
        catch (Throwable $exception) { $errors['government_id_document'] = $exception->getMessage(); $document = null; }
        if (!$photo) $errors['profile_photo'] = 'Profile photo is required.';
        if (!$document) $errors['government_id_document'] = 'Government ID document is required.';
        if ($errors) referrer_validation_response($errors, $expectsJson);

        $data = [$values['full_name'], $values['date_of_birth'], $values['gender'], $values['mobile_number'], $values['alternate_mobile'] ?: null, $values['email'], $photo, $values['address'], $values['city'], $values['state'], $values['country'], $values['pincode'], $values['occupation'], json_encode($selectedCategories, JSON_THROW_ON_ERROR), $values['experience_level'], $values['bio'], $values['government_id_type'], strtoupper($values['government_id_number']), $document, $values['bank_account_name'], $values['bank_account_number'], $values['ifsc_code'], $values['upi_id'] ?: null, 1, $user['id']];
        $sql = $existing ? 'UPDATE referrer_profiles SET full_name=?,date_of_birth=?,gender=?,mobile_number=?,alternate_mobile=?,email=?,profile_photo=?,address=?,city=?,state=?,country=?,pincode=?,occupation=?,service_categories=?,experience_level=?,bio=?,government_id_type=?,government_id_number=?,government_id_document=?,bank_account_name=?,bank_account_number=?,ifsc_code=?,upi_id=?,is_profile_completed=? WHERE user_id=?' : 'INSERT INTO referrer_profiles (full_name,date_of_birth,gender,mobile_number,alternate_mobile,email,profile_photo,address,city,state,country,pincode,occupation,service_categories,experience_level,bio,government_id_type,government_id_number,government_id_document,bank_account_name,bank_account_number,ifsc_code,upi_id,is_profile_completed,user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        db()->prepare($sql)->execute($data);
        $redirect = url('referrer/dashboard.php');
        if ($expectsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'redirect' => $redirect], JSON_THROW_ON_ERROR);
            exit;
        }
        set_flash('success', $existing ? 'Referrer profile updated successfully.' : 'Your referrer profile is ready.');
        redirect('referrer/dashboard.php');
    } catch (Throwable $exception) {
        app_log('Referrer profile update failed: ' . $exception->getMessage());
        if ($expectsJson) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'errors' => ['general' => 'We could not save your profile. Please try again.']], JSON_THROW_ON_ERROR);
            exit;
        }
        if (!$errors) $errors['general'] = 'We could not save your profile. Please try again.';
    }
}

if ($values['date_of_birth'] !== '') $values['date_of_birth'] = referrer_format_date_for_input((string) $values['date_of_birth']);
$selectedCategoryValues = $selectedCategories;
if (array_filter($selectedCategories, static fn($category): bool => !in_array($category, REFERRER_SERVICE_CATEGORIES, true))) {
    $values['service_category_other'] = (string) end($selectedCategories);
    $selectedCategoryValues = array_values(array_filter($selectedCategories, static fn($category): bool => in_array($category, REFERRER_SERVICE_CATEGORIES, true)));
    $selectedCategoryValues[] = 'Other';
}

$pageTitle = ($existing ? 'Edit' : 'Complete') . ' referrer profile | ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.profile-wizard .upload-card.is-invalid { border-color: #dc3545; box-shadow: 0 0 0 .2rem rgba(220, 53, 69, .12); }
.profile-wizard .field-error { color: #dc3545; display: block; font-size: .875em; margin-top: .25rem; }
</style>
<main class="profile-page"><div class="container py-5"><div class="profile-hero"><div><span class="eyebrow"><i class="bi bi-person-check"></i> Referrer profile</span><h1><?= $existing ? 'Keep your profile current.' : 'Complete your referrer profile.' ?></h1><p>Six quick steps to unlock referral opportunities.</p></div><?php if ($existing): ?><a href="<?= e(url('referrer/dashboard.php')) ?>" class="btn btn-light border">Back to dashboard</a><?php endif; ?></div>
<?php if (isset($errors['general'])): ?><div class="alert alert-danger mt-4"><?= e($errors['general']) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="profile-wizard needs-validation mt-4" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="wizard-progress six-step"><?php for ($i = 1; $i <= 6; $i++): ?><span class="<?= $i === 1 ? 'active' : '' ?>"><?= $i ?></span><?php endfor; ?></div>
<section class="wizard-step active" data-step="1"><div class="wizard-heading"><span>Step 1 of 6</span><h2>Personal Information</h2><p>Tell Us How To Contact You.</p></div><div class="row g-3">
<?php foreach (['full_name' => 'Full Name', 'date_of_birth' => 'Date of Birth', 'mobile_number' => 'Mobile Number', 'alternate_mobile' => 'Alternate Mobile (Optional)', 'email' => 'Email Address'] as $field => $label): ?><div class="col-md-6"><label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label><input id="<?= e($field) ?>" class="form-control" type="text" name="<?= e($field) ?>" value="<?= e($values[$field]) ?>" placeholder="<?= $field === 'date_of_birth' ? 'DD/MM/YYYY' : '' ?>" <?= $field === 'alternate_mobile' ? '' : 'required' ?> <?= $field === 'full_name' ? 'minlength="2" maxlength="100"' : ($field === 'date_of_birth' ? 'maxlength="10" inputmode="numeric" pattern="\\d{2}/\\d{2}/\\d{4}"' : ($field === 'mobile_number' || $field === 'alternate_mobile' ? 'pattern="[0-9+() .-]{7,25}" maxlength="25"' : 'maxlength="150"')) ?>></div><?php endforeach; ?>
<div class="col-md-6"><label class="form-label" for="gender">Gender</label><select id="gender" class="form-select" name="gender" required><option value="">Choose Gender</option><?php foreach (REFERRER_GENDERS as $option): ?><option value="<?= e($option) ?>" <?= $values['gender'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div></div></section>
<section class="wizard-step" data-step="2"><div class="wizard-heading"><span>Step 2 of 6</span><h2>Address</h2><p>Where Are You Based?</p></div><div class="row g-3"><div class="col-12"><label class="form-label" for="address">Address / House No. / Building Name</label><input id="address" class="form-control" name="address" required maxlength="255" value="<?= e($values['address']) ?>"></div><div class="col-md-6"><label class="form-label" for="pincode">PIN Code</label><div class="position-relative"><input id="pincode" class="form-control" name="pincode" required maxlength="6" inputmode="numeric" pattern="\d{6}" placeholder="Enter 6-digit PIN Code" value="<?= e($values['pincode']) ?>" data-pincode-lookup><span class="pincode-spinner position-absolute top-50 end-0 translate-middle-y me-3" hidden><span class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></span></span><div class="pincode-feedback small mt-1" hidden></div></div></div><div class="col-md-6"><label class="form-label" for="city">City</label><input id="city" class="form-control" name="city" required maxlength="100" value="<?= e($values['city']) ?>" data-pincode-city></div><div class="col-md-6"><label class="form-label" for="state">State</label><input id="state" class="form-control" name="state" required maxlength="100" value="<?= e($values['state']) ?>" data-pincode-state></div><div class="col-md-6"><label class="form-label" for="country">Country</label><input id="country" class="form-control" name="country" required maxlength="100" value="<?= e($values['country'] ?: 'India') ?>" readonly data-pincode-country></div></div></section>
<section class="wizard-step" data-step="3"><div class="wizard-heading"><span>Step 3 of 6</span><h2>Professional Information</h2><p>Select Every Service Area Where You Can Make Valuable Introductions.</p></div><div class="row g-3"><div class="col-md-6"><label class="form-label" for="occupation">Occupation</label><input id="occupation" class="form-control" name="occupation" required maxlength="150" value="<?= e($values['occupation']) ?>"></div><div class="col-md-6"><label class="form-label" for="experience_level">Experience Level</label><select id="experience_level" class="form-select" name="experience_level" required><option value="">Choose Experience</option><?php foreach (REFERRER_EXPERIENCE_LEVELS as $option): ?><option value="<?= e($option) ?>" <?= $values['experience_level'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div></div><label class="form-label required-label mt-4">Choose Service Categories</label><div class="category-grid referrer-categories" data-error-key="service_categories"><?php foreach (REFERRER_SERVICE_CATEGORIES as $category): ?><label><input type="checkbox" name="service_categories[]" value="<?= e($category) ?>" <?= in_array($category, $selectedCategoryValues, true) ? 'checked' : '' ?><?= $category === 'Other' ? ' data-other-target="#referrer-category-other"' : '' ?>><span><?= e($category) ?></span></label><?php endforeach; ?></div><div id="referrer-category-other" class="mt-3" hidden><label class="form-label required-label" for="service_category_other">Please specify category</label><input id="service_category_other" class="form-control" name="service_category_other" maxlength="100" value="<?= e($values['service_category_other']) ?>"></div><div class="mt-4"><label class="form-label" for="bio">Professional Bio</label><textarea id="bio" class="form-control" name="bio" required minlength="20" maxlength="3000" rows="5"><?= e($values['bio']) ?></textarea></div></section>
<section class="wizard-step" data-step="4"><div class="wizard-heading"><span>Step 4 of 6</span><h2>Identity Verification</h2><p>Your Document Is Securely Stored For Verification.</p></div><div class="row g-3"><div class="col-md-6"><label class="form-label" for="government_id_type">Government ID Type</label><select id="government_id_type" class="form-select" name="government_id_type" required><option value="">Choose ID Type</option><?php foreach (REFERRER_ID_TYPES as $option): ?><option value="<?= e($option) ?>" <?= $values['government_id_type'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label" for="government_id_number">Government ID Number</label><input id="government_id_number" class="form-control" name="government_id_number" required pattern="[A-Za-z0-9 -]{4,100}" maxlength="100" value="<?= e($values['government_id_number']) ?>"></div><div class="col-12"><label class="upload-card compact-upload"><input type="file" name="government_id_document" accept="image/png,image/jpeg,application/pdf" <?= $existing && $existing['government_id_document'] ? '' : 'required' ?>><i class="bi bi-file-earmark-check"></i><strong>Upload Verification Document</strong><small>PNG, JPG, JPEG, or PDF · up to 5 MB</small><?php if ($existing && $existing['government_id_document']): ?><em>Document Already Uploaded</em><?php endif; ?></label></div></div></section>
<section class="wizard-step" data-step="5"><div class="wizard-heading"><span>Step 5 of 6</span><h2>Payment Information</h2><p>Use An Account In Your Own Name For Future Earnings.</p></div><div class="row g-3"><div class="col-md-6"><label class="form-label" for="bank_account_name">Bank Account Holder Name</label><input id="bank_account_name" class="form-control" name="bank_account_name" required minlength="2" maxlength="150" value="<?= e($values['bank_account_name']) ?>"></div><div class="col-md-6"><label class="form-label" for="bank_account_number">Account Number</label><input id="bank_account_number" class="form-control" name="bank_account_number" required pattern="[0-9]{9,34}" maxlength="34" value="<?= e($values['bank_account_number']) ?>"></div><div class="col-md-6"><label class="form-label" for="ifsc_code">IFSC Code</label><input id="ifsc_code" class="form-control text-uppercase" name="ifsc_code" required pattern="[A-Za-z]{4}0[A-Za-z0-9]{6}" minlength="11" maxlength="11" value="<?= e($values['ifsc_code']) ?>"></div><div class="col-md-6"><label class="form-label" for="upi_id">UPI ID (Optional)</label><input id="upi_id" class="form-control" name="upi_id" pattern="[a-zA-Z0-9._-]{2,100}@[a-zA-Z]{2,50}" maxlength="151" value="<?= e($values['upi_id']) ?>"></div></div></section>
<section class="wizard-step" data-step="6"><div class="wizard-heading"><span>Step 6 of 6</span><h2>Profile Photo &amp; Review</h2><p>Finish With A Clear Profile Photo, Then Review Your Details Before Submitting.</p></div><div class="row g-4 align-items-stretch"><div class="col-md-5"><label class="upload-card"><input type="file" name="profile_photo" accept="image/png,image/jpeg" <?= $existing && $existing['profile_photo'] ? '' : 'required' ?> data-preview="photo-preview"><i class="bi bi-person-circle"></i><strong>Upload Profile Picture</strong><small>PNG, JPG, or JPEG · up to 2 MB</small><?php if ($existing && $existing['profile_photo']): ?><img id="photo-preview" src="<?= e(url($existing['profile_photo'])) ?>" alt="Current Profile Photo"><?php else: ?><img id="photo-preview" alt="Profile Photo Preview" hidden><?php endif; ?></label></div><div class="col-md-7"><div class="review-summary"><span class="eyebrow">Review Summary</span><h3><?= e($values['full_name']) ?></h3><p><?= e($values['occupation']) ?><?= $values['experience_level'] ? ' · ' . e($values['experience_level']) : '' ?></p><dl><div><dt>Email</dt><dd><?= e($values['email']) ?></dd></div><div><dt>Service Areas</dt><dd><?= e(implode(', ', $selectedCategories) ?: 'Not Selected') ?></dd></div><div><dt>Verification</dt><dd>Pending Review After Submission</dd></div></dl></div></div></div></section>
<div class="wizard-actions"><button type="button" class="btn btn-light border wizard-back" hidden><i class="bi bi-arrow-left"></i> Back</button><button type="button" class="btn btn-primary wizard-next">Continue <i class="bi bi-arrow-right"></i></button><button type="submit" class="btn btn-primary wizard-submit" hidden>Save &amp; Finish <i class="bi bi-check2-circle"></i></button></div></form></div></main>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const wizard = document.querySelector('.profile-wizard');
  if (!wizard) return;
  const serverErrors = <?= json_encode($errors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  let current = 1;
  const steps = [...wizard.querySelectorAll('.wizard-step')];
  const progress = [...wizard.querySelectorAll('.wizard-progress span')];
  const back = wizard.querySelector('.wizard-back');
  const next = wizard.querySelector('.wizard-next');
  const submit = wizard.querySelector('.wizard-submit');
  const show = (step) => { current = step; steps.forEach((item) => item.classList.toggle('active', Number(item.dataset.step) === step)); progress.forEach((item, index) => item.classList.toggle('active', index < step)); back.hidden = step === 1; next.hidden = step === steps.length; submit.hidden = step !== steps.length; };
  const clearErrors = () => { wizard.querySelectorAll('.is-invalid').forEach((item) => item.classList.remove('is-invalid')); wizard.querySelectorAll('.field-error').forEach((item) => item.remove()); };
  const targetFor = (key) => key === 'service_categories' ? wizard.querySelector('[data-error-key="service_categories"]') : wizard.querySelector(`[name="${key}"]`);
  const displayErrors = (errors) => {
    clearErrors();
    const firstKey = Object.keys(errors)[0];
    Object.entries(errors).forEach(([key, message]) => {
      const target = targetFor(key); if (!target) return;
      const control = key === 'service_categories' ? target : (target.type === 'file' ? target.closest('.upload-card') : target);
      control.classList.add('is-invalid');
      const feedback = document.createElement('div'); feedback.className = 'field-error'; feedback.textContent = message; control.insertAdjacentElement('afterend', feedback);
    });
    const first = targetFor(firstKey); const step = first?.closest('.wizard-step'); if (step) show(Number(step.dataset.step));
  };
  const clientErrors = (scope = wizard) => {
    const errors = {};
    const value = (name) => wizard.elements[name]?.value.trim() || '';
    const match = (name, expression, message) => { if (value(name) && !expression.test(value(name))) errors[name] = message; };
    if (scope.querySelector('[name="full_name"]') && (value('full_name').length < 2 || value('full_name').length > 100)) errors.full_name = 'Full name must be between 2 and 100 characters.';
    if (scope.querySelector('[name="date_of_birth"]')) {
      const dobValue = value('date_of_birth');
      if (!dobValue) errors.date_of_birth = 'Date of birth must be a valid date in the past.';
      else {
        const dobPattern = /^\d{2}\/\d{2}\/\d{4}$/;
        if (!dobPattern.test(dobValue)) errors.date_of_birth = 'Date of birth must be a valid date in the past.';
        else {
          const [day, month, year] = dobValue.split('/').map(Number);
          const parsedDob = new Date(year, month - 1, day);
          if (parsedDob.getFullYear() !== year || parsedDob.getMonth() !== month - 1 || parsedDob.getDate() !== day || parsedDob > new Date()) errors.date_of_birth = 'Date of birth must be a valid date in the past.';
        }
      }
    }
    if (scope.querySelector('[name="mobile_number"]')) { if (!value('mobile_number')) errors.mobile_number = 'Mobile number is required.'; else match('mobile_number', /^[0-9+() .-]{7,25}$/, 'Mobile number format is invalid.'); match('alternate_mobile', /^[0-9+() .-]{7,25}$/, 'Alternate mobile number format is invalid.'); if (!value('email') || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value('email'))) errors.email = 'Email address is invalid.'; if (!value('gender')) errors.gender = 'Please choose a gender.'; }
    const textLimits = { address: 255, city: 100, state: 100, country: 100, pincode: 20, occupation: 150 };
    Object.entries(textLimits).forEach(([name, limit]) => { if (!scope.querySelector(`[name="${name}"]`)) return; const label = name.replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase()); if (!value(name)) errors[name] = `${label} is required.`; else if (value(name).length > limit) errors[name] = `${label} must be ${limit} characters or fewer.`; });
    if (scope.querySelector('[name="experience_level"]') && !value('experience_level')) errors.experience_level = 'Please choose an experience level.';
    if (scope.querySelector('[name="service_categories[]"]') && ![...wizard.querySelectorAll('[name="service_categories[]"]')].some((field) => field.checked)) errors.service_categories = 'Choose at least one service category.';
    const otherCategory = wizard.querySelector('[name="service_categories[]"][value="Other"]');
    if (otherCategory?.checked && value('service_category_other').length === 0) errors.service_categories = 'Please specify a service category.';
    if (scope.querySelector('[name="bio"]') && (value('bio').length < 20 || value('bio').length > 3000)) errors.bio = 'Professional bio must be between 20 and 3,000 characters.';
    if (scope.querySelector('[name="government_id_type"]') && !value('government_id_type')) errors.government_id_type = 'Please choose a government ID type.';
    if (scope.querySelector('[name="government_id_number"]')) { if (!value('government_id_number')) errors.government_id_number = 'Government ID Number is required.'; else match('government_id_number', /^[A-Za-z0-9 -]{4,100}$/, 'Government ID Number format is invalid.'); }
    if (scope.querySelector('[name="bank_account_name"]') && (value('bank_account_name').length < 2 || value('bank_account_name').length > 150)) errors.bank_account_name = 'Bank account holder name must be between 2 and 150 characters.';
    if (scope.querySelector('[name="bank_account_number"]') && !/^[0-9]{9,34}$/.test(value('bank_account_number'))) errors.bank_account_number = 'Bank account number must contain 9 to 34 digits.';
    if (scope.querySelector('[name="ifsc_code"]')) { const ifsc = value('ifsc_code').toUpperCase(); wizard.elements.ifsc_code.value = ifsc; if (!/^[A-Z]{4}0[A-Z0-9]{6}$/.test(ifsc)) errors.ifsc_code = 'IFSC Code must be 11 characters in a valid format.'; }
    if (scope.querySelector('[name="upi_id"]')) match('upi_id', /^[a-zA-Z0-9._-]{2,100}@[a-zA-Z]{2,50}$/, 'UPI ID format is invalid.');
    const uploads = { profile_photo: { label: 'Profile photo', size: 2, types: ['image/png', 'image/jpeg'] }, government_id_document: { label: 'Government ID document', size: 5, types: ['image/png', 'image/jpeg', 'application/pdf'] } };
    Object.entries(uploads).forEach(([name, rules]) => { const input = wizard.elements[name]; if (!scope.contains(input)) return; const file = input.files[0]; if (input.required && !file) errors[name] = `${rules.label} is required.`; else if (file && file.size > rules.size * 1024 * 1024) errors[name] = `${rules.label} must be ${rules.size} MB or smaller.`; else if (file && !rules.types.includes(file.type)) errors[name] = name === 'profile_photo' ? 'Upload a PNG, JPG, or JPEG profile photo.' : 'Upload a PNG, JPG, JPEG, or PDF verification document.'; });
    return errors;
  };
  next.addEventListener('click', (event) => { event.preventDefault(); event.stopImmediatePropagation(); const errors = clientErrors(steps[current - 1]); if (Object.keys(errors).length) return displayErrors(errors); clearErrors(); show(current + 1); }, true);
  back.addEventListener('click', (event) => { event.preventDefault(); event.stopImmediatePropagation(); clearErrors(); show(current - 1); }, true);
  wizard.addEventListener('submit', async (event) => { event.preventDefault(); event.stopImmediatePropagation(); const errors = clientErrors(); if (Object.keys(errors).length) return displayErrors(errors); clearErrors(); const response = await fetch(wizard.action || window.location.href, { method: 'POST', body: new FormData(wizard), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }); const payload = await response.json(); if (!response.ok || !payload.success) return displayErrors(payload.errors || { general: 'We could not save your profile. Please try again.' }); window.location.assign(payload.redirect); }, true);
  if (Object.keys(serverErrors).length) displayErrors(serverErrors);
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
