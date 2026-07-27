<?php
declare(strict_types=1);

$expectsJson = $_SERVER['REQUEST_METHOD'] === 'POST'
    && (
        str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    );
if ($expectsJson) {
    // Keep accidental PHP output (for example a configured display_error warning)
    // out of the API body. It is logged when the response is emitted below.
    ob_start();
    register_shutdown_function(static function (): void {
        $error = error_get_last();
        $fatalErrorTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if ($error === null || !in_array($error['type'], $fatalErrorTypes, true) || headers_sent()) {
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['success' => false, 'message' => 'The referral could not be updated.']);
    });
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/business_profile.php';
require_once __DIR__ . '/../includes/customer_referrals.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/notifications.php';

function referral_update_response(bool $expectsJson, bool $success, string $message, int $status = 200, array $data = []): never
{
    if ($expectsJson) {
        $unexpectedOutput = ob_get_contents();
        if ($unexpectedOutput !== false && $unexpectedOutput !== '') {
            app_log('Discarded unexpected output from referral status API: ' . substr(strip_tags($unexpectedOutput), 0, 500));
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data), JSON_THROW_ON_ERROR);
        exit;
    }
    if ($success) set_flash('success', $message);
    else set_flash('danger', $message);
    redirect('business/referral_view.php?id=' . (int) ($_GET['id'] ?? 0));
}

if ($expectsJson) {
    set_exception_handler(static function (Throwable $exception): never {
        app_log('Unhandled referral status API failure: ' . $exception->getMessage());
        referral_update_response(true, false, 'The referral could not be updated.', 500);
    });
}

$user = current_user();
if ($expectsJson && $user === null) {
    referral_update_response(true, false, 'Your session has expired. Please sign in again.', 401);
}
if ($expectsJson && $user !== null && canonical_role((string) ($user['role'] ?? '')) !== 'BUSINESS') {
    referral_update_response(true, false, 'You do not have permission to update referrals.', 403);
}
$user = require_login('BUSINESS');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$referral = business_referral($id, (int) $user['id']);
if (!$referral) {
    if ($expectsJson) referral_update_response(true, false, 'That referral was not found.', 404);
    set_flash('danger', 'That referral was not found.');
    redirect('business/referrals.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ActivityLogService::logActivity((int) $user['id'], 'BUSINESS', 'Referral', 'Referral Viewed', 'Referral', (int) $referral['id'], 'Viewed referral details.');
}
$completionValues = ['sale_amount' => '', 'invoice_number' => '', 'sale_date' => '', 'completion_notes' => ''];
$referralReviewStatuses = ['Under Review', 'Processing', 'Accepted', 'Rejected', 'Completed'];
$contactVisible = is_contact_visible($referral);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if (($_POST['action'] ?? '') === 'complete') {
            [$completionValues, $completionErrors] = validate_referral_completion($_POST);
            if ($completionErrors) throw new RuntimeException((string) reset($completionErrors));
            $commissionAmount = complete_referral_with_commission((int) $referral['id'], (int) $user['id'], $completionValues);
            ActivityLogService::logActivity((int) $user['id'], 'BUSINESS', 'Referral', 'Referral Completed', 'Referral', (int) $referral['id'], 'Referral completed.');
            referral_update_response($expectsJson, true, 'Referral completed and ' . opportunity_money($commissionAmount) . ' credited to the referrer wallet.', 200, [
                'current_status' => 'Completed',
                'allowed_transitions' => [],
            ]);
        }

        if (($_POST['action'] ?? '') === 'request_contact') {
            // Request Contact Access — sends email to customer
            if ($referral['status'] !== 'Processing') throw new RuntimeException('Referral must be in Processing status to request contact access.');
            if (empty($referral['customer_email'])) throw new RuntimeException('Customer email is not available for this referral.');

            require_once __DIR__ . '/../includes/email_service.php';
            $token = generate_approval_token((int) $referral['id']);
            $approveLink = absolute_url('api/customer_consent.php?token=' . $token . '&action=approve');
            $declineLink = absolute_url('api/customer_consent.php?token=' . $token . '&action=decline');

            // Send consent email to customer FIRST — if it fails, don't change status
            $profile = business_profile((int) $user['id']);
            $bName = $profile['business_name'] ?? $user['full_name'];
            $emailBody = '<p>Hello <strong>' . e($referral['customer_name']) . '</strong>,</p>'
                . '<p><strong>' . e($bName) . '</strong> would like to contact you regarding a referral opportunity.</p>'
                . '<p><strong>Opportunity:</strong> ' . e($referral['opportunity_title']) . '<br>'
                . '<strong>Referral ID:</strong> ' . e($referral['referral_code']) . '</p>'
                . '<p>By approving, you allow this business to see your phone number and email to discuss the opportunity with you.</p>'
                . '<p style="margin:28px 0"><a href="' . e($approveLink) . '" style="background:#28a745;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;display:inline-block;margin-right:12px">Approve</a> <a href="' . e($declineLink) . '" style="background:#dc3545;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;display:inline-block">Decline</a></p>'
                . '<p style="font-size:12px;color:#718096">This link expires in 7 days. If you do not respond, your details will remain private.</p>';

            $emailHtml = NotificationService::publicEmailTemplate('Contact Access Request', $emailBody, '');
            try {
                (new EmailService())->send(
                    $referral['customer_email'],
                    $referral['customer_name'],
                    'A business wants to connect with you | ' . APP_NAME,
                    $emailHtml,
                    strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $emailBody))
                );
            } catch (Throwable $emailException) {
                // Revert the approval token since email failed
                db()->prepare('UPDATE customer_referrals SET customer_approval_token = NULL, customer_approval_token_expires_at = NULL, customer_approval_status = ? WHERE id = ?')
                    ->execute(['pending', (int) $referral['id']]);
                $errorDetail = $emailException->getMessage();
                app_log('Customer consent email failed: ' . $errorDetail);
                throw new RuntimeException('Could not send approval email to customer: ' . $errorDetail);
            }

            // Email sent successfully — now update status
            db()->prepare("UPDATE customer_referrals SET status = 'Waiting for Customer Approval' WHERE id = ? AND business_id = ?")->execute([$referral['id'], $user['id']]);
            add_referral_history((int) $referral['id'], 'Waiting for Customer Approval', 'Business requested customer contact access. Approval email sent to ' . $referral['customer_email'] . '.');

            ActivityLogService::logActivity((int) $user['id'], 'BUSINESS', 'Referral', 'Contact Access Requested', 'Referral', (int) $referral['id'], 'Requested customer consent. Email sent to ' . $referral['customer_email'] . '.');
            referral_update_response($expectsJson, true, 'Contact access request email sent to ' . $referral['customer_email'] . '. Awaiting customer approval.', 200, [
                'current_status' => 'Waiting for Customer Approval',
                'allowed_transitions' => [],
            ]);
        }

        $status = trim((string) ($_POST['status'] ?? ''));
        if ($status === 'Completed') throw new RuntimeException('Use the completion form to complete this referral.');
        $allowedTransitions = permitted_referral_statuses($referral['status']);
        if (!in_array($status, $allowedTransitions, true)) throw new RuntimeException('Choose a valid referral status.');
        if ($status === $referral['status']) {
            referral_update_response($expectsJson, true, 'No referral status change was made.', 200, [
                'current_status' => $status,
                'allowed_transitions' => permitted_referral_statuses($status),
            ]);
        }

        $stmt = db()->prepare('UPDATE customer_referrals SET status = ? WHERE id = ? AND business_id = ?');
        $stmt->execute([$status, $referral['id'], $user['id']]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('The referral status could not be updated.');
        add_referral_history((int) $referral['id'], $status);
        ActivityLogService::logActivity((int) $user['id'], 'BUSINESS', 'Referral', 'Referral ' . $status, 'Referral', (int) $referral['id'], 'Referral marked ' . strtolower($status) . '.');
        try {
            NotificationService::referralStatus((int) $referral['referrer_id'], $status, ['id' => $referral['id'], 'customer_name' => $referral['customer_name'], 'campaign' => $referral['opportunity_title'], 'product' => $referral['product_name'] ?: 'Legacy referral']);
        } catch (Throwable $notificationException) {
            app_log('Referral status notification failed: ' . $notificationException->getMessage());
        }
        referral_update_response($expectsJson, true, 'Referral status updated to ' . $status . '.', 200, [
            'current_status' => $status,
            'allowed_transitions' => permitted_referral_statuses($status),
        ]);
    } catch (Throwable $exception) {
        app_log('Referral update failed: ' . $exception->getMessage());
        referral_update_response($expectsJson, false, $exception instanceof RuntimeException ? $exception->getMessage() : 'The referral could not be updated.', 422);
    }
}

$pageTitle = 'Referral #' . $referral['id'] . ' | ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<main class="opportunity-page"><div class="container py-5">
  <div class="opportunity-hero"><div><span class="eyebrow">Referral #<?= (int) $referral['id'] ?></span><h1><?= e($referral['customer_name']) ?></h1><p><?= e($referral['opportunity_title']) ?></p></div><a class="btn btn-light border" href="<?= e(url('business/referrals.php')) ?>">Back to Referrals</a></div>
  <div class="row g-4 mt-1"><div class="col-lg-8"><section class="detail-card"><h2>Referral Details</h2><dl class="detail-list">
    <div><dt>Product / Service</dt><dd><?= e($referral['product_name'] ?: 'Legacy referral') ?></dd></div><div><dt>Commission Rate</dt><dd><?= $referral['commission_percentage'] !== null ? e($referral['commission_percentage']) . '%' : 'Fixed legacy reward' ?></dd></div><div><dt>Sale Amount</dt><dd><?= $referral['sale_amount'] !== null ? e(opportunity_money($referral['sale_amount'])) : 'Not entered' ?></dd></div><div><dt>Gross Commission</dt><dd><?= $referral['calculated_commission'] !== null ? e(opportunity_money($referral['calculated_commission'])) : 'Pending completion' ?></dd></div>
    <?php if ($referral['status'] === 'Completed'): ?><div><dt>Platform Fee (2%)</dt><dd><?= e(opportunity_money($referral['platform_fee'] ?? 0)) ?></dd></div><div><dt>Net Commission (Wallet Credit)</dt><dd><?= e(opportunity_money($referral['net_commission'] ?? $referral['calculated_commission'])) ?></dd></div><div><dt>Invoice Number</dt><dd><?= e($referral['invoice_number'] ?: 'Not provided') ?></dd></div><div><dt>Sale Date</dt><dd><?= $referral['sale_date'] ? e(date('d M Y', strtotime($referral['sale_date']))) : 'Not provided' ?></dd></div><div><dt>Completed</dt><dd><?= e(date('d M Y, h:i A', strtotime($referral['completed_at']))) ?></dd></div><?php endif; ?>
    <div><dt>Customer</dt><dd><?= e($referral['customer_name']) ?> · <?php if ($contactVisible): ?><?= e($referral['customer_phone']) ?><?php else: ?><span class="text-muted"><?= e(mask_phone($referral['customer_phone'])) ?></span> <i class="bi bi-lock-fill text-warning" title="Hidden until customer approves"></i><?php endif; ?></dd></div>
    <?php if ($referral['customer_email']): ?><div><dt>Email</dt><dd><?php if ($contactVisible): ?><?= e($referral['customer_email']) ?><?php else: ?><span class="text-muted"><?= e(mask_email($referral['customer_email'])) ?></span> <i class="bi bi-lock-fill text-warning"></i><?php endif; ?></dd></div><?php endif; ?>
    <?php if ($contactVisible && $referral['customer_address']): ?><div><dt>Address</dt><dd><?= e($referral['customer_address']) ?>, <?= e($referral['customer_city']) ?>, <?= e($referral['customer_state']) ?></dd></div><?php endif; ?>
    <div><dt>City</dt><dd><?= e($referral['customer_city']) ?></dd></div>
    <div><dt>Referral ID</dt><dd><code><?= e($referral['referral_code']) ?></code></dd></div>
    <div><dt>Approval Status</dt><dd><span class="role-badge"><?= e(ucfirst($referral['customer_approval_status'])) ?></span></dd></div></dl></section></div>
  <div class="col-lg-4"><section class="detail-card"><h2>Review Referral</h2><p class="small text-muted">Workflow: Submitted → Under Review → Processing → Request Contact → Customer Approved → Accepted → Completed.</p><div id="referral-status-feedback" aria-live="polite"></div>
  <?php if ($referral['status'] === 'Processing'): ?>
  <form method="post" data-referral-update><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="request_contact"><button class="btn btn-warning w-100 mt-3" type="submit"><i class="bi bi-shield-lock"></i> Request Contact Access</button><p class="small text-muted mt-2">An email will be sent to the customer requesting permission to share their contact details with you.</p></form>
  <hr>
  <?php endif; ?>
  <?php $allowedTransitions = permitted_referral_statuses($referral['status']); ?>
  <?php if ($allowedTransitions): ?>
  <form method="post" data-referral-update><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="status"><label class="form-label" for="status">Referral Status</label><select class="form-select" id="status" name="status" required><option value="">Choose next status</option><?php foreach ($allowedTransitions as $item): ?><option value="<?= e($item) ?>"<?= $item === 'Completed' ? ' data-requires-completion="1"' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select><button class="btn btn-primary w-100 mt-3" type="submit">Update Status</button></form>
  <?php elseif ($referral['status'] === 'Waiting for Customer Approval'): ?>
  <div class="alert alert-info mt-3"><i class="bi bi-hourglass-split"></i> Waiting for the customer to approve sharing their contact details.</div>
  <?php elseif ($referral['status'] === 'Declined by Customer'): ?>
  <div class="alert alert-danger mt-3"><i class="bi bi-x-circle"></i> The customer declined sharing their contact details. This referral cannot proceed.</div>
  <?php elseif ($referral['status'] === 'Completed'): ?>
  <div class="alert alert-success mt-3"><i class="bi bi-check-circle"></i> This referral has been completed and paid.</div>
  <?php endif; ?>
  </section></div></div></div></main>
<div class="modal fade" id="completionModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="modal-header"><h2 class="modal-title fs-5">Pay Commission &amp; Complete Referral</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">
<p class="small text-muted">Commission: sale amount × <?= e((string) $referral['commission_percentage']) ?>%. A 2% platform fee is deducted. You pay the net commission via Razorpay.</p>
<div id="pay-feedback" aria-live="polite"></div>
<label class="form-label" for="pay_sale_amount">Final Sale Amount</label><div class="input-group"><span class="input-group-text">₹</span><input class="form-control" id="pay_sale_amount" type="number" min="0.01" step="0.01" required></div>
<div id="commission-preview" class="mt-3 p-3 border rounded" hidden><dl class="detail-list mb-0"><div><dt>Gross Commission</dt><dd id="preview-gross">—</dd></div><div><dt>Platform Fee (2%)</dt><dd id="preview-fee">—</dd></div><div><dt>Net Commission (You Pay)</dt><dd id="preview-net" class="fw-bold text-primary">—</dd></div></dl></div>
<label class="form-label mt-3" for="pay_invoice_number">Invoice Number <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" id="pay_invoice_number" maxlength="100">
<label class="form-label mt-3" for="pay_sale_date">Sale Date <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" id="pay_sale_date" type="date" max="<?= e(date('Y-m-d')) ?>">
<label class="form-label mt-3" for="pay_completion_notes">Notes <span class="text-muted fw-normal">(optional)</span></label><textarea class="form-control" id="pay_completion_notes" rows="2" maxlength="5000"></textarea>
</div><div class="modal-footer"><button class="btn btn-light border" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success" id="pay-commission-btn" type="button" disabled><i class="bi bi-credit-card"></i> Pay Commission</button></div></div></div></div>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
const feedback = document.querySelector('#referral-status-feedback');
const showFeedback = (kind, message) => {
  feedback.replaceChildren();
  const alert = document.createElement('div');
  alert.className = `alert alert-${kind}`;
  alert.textContent = message;
  feedback.appendChild(alert);
};
const renderTransitions = (transitions) => {
  const form = document.querySelector('form[data-referral-update]');
  const select = form?.querySelector('select[name="status"]');
  if (!form || !select) return;
  select.replaceChildren(new Option('Choose next status', ''));
  if (!transitions || !transitions.length) {
    form.querySelector('[type="submit"]').disabled = true;
    return;
  }
  transitions.forEach((status) => {
    const option = new Option(status, status);
    if (status === 'Completed') option.dataset.requiresCompletion = '1';
    select.add(option);
  });
};

// Commission preview calculation
const saleInput = document.getElementById('pay_sale_amount');
const previewDiv = document.getElementById('commission-preview');
const payBtn = document.getElementById('pay-commission-btn');
const commRate = <?= (float) $referral['commission_percentage'] ?>;

saleInput?.addEventListener('input', () => {
  const sale = parseFloat(saleInput.value) || 0;
  if (sale > 0) {
    const gross = Math.round(sale * commRate) / 100;
    const fee = Math.round(gross * 2) / 100;
    const net = Math.round((gross - fee) * 100) / 100;
    document.getElementById('preview-gross').textContent = '₹' + gross.toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('preview-fee').textContent = '₹' + fee.toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('preview-net').textContent = '₹' + net.toLocaleString('en-IN', {minimumFractionDigits: 2});
    previewDiv.hidden = false;
    payBtn.disabled = net <= 0;
  } else {
    previewDiv.hidden = true;
    payBtn.disabled = true;
  }
});

// Pay Commission button — create order then open Razorpay
payBtn?.addEventListener('click', async () => {
  const sale = parseFloat(saleInput.value) || 0;
  if (sale <= 0) return;
  const payFeedback = document.getElementById('pay-feedback');
  payFeedback.innerHTML = '';
  payBtn.disabled = true; payBtn.textContent = 'Creating order…';

  try {
    const formData = new FormData();
    formData.append('referral_id', '<?= (int) $referral['id'] ?>');
    formData.append('sale_amount', saleInput.value);

    const orderResp = await fetch('<?= e(url('api/razorpay_order.php')) ?>', {method: 'POST', body: formData});
    const orderData = await orderResp.json();
    if (!orderResp.ok || !orderData.success) throw new Error(orderData.message || 'Could not create payment order.');

    // Shared verify function
    const verifyPayment = async (orderId, paymentId, sig, method) => {
      payBtn.textContent = 'Verifying payment…';
      const verifyData = new FormData();
      verifyData.append('razorpay_order_id', orderId);
      verifyData.append('razorpay_payment_id', paymentId);
      verifyData.append('razorpay_signature', sig);
      verifyData.append('payment_method', method);
      verifyData.append('sale_amount', saleInput.value);
      verifyData.append('invoice_number', document.getElementById('pay_invoice_number').value);
      verifyData.append('sale_date', document.getElementById('pay_sale_date').value);
      verifyData.append('completion_notes', document.getElementById('pay_completion_notes').value);
      const verifyResp = await fetch('<?= e(url('api/razorpay_verify.php')) ?>', {method: 'POST', body: verifyData});
      const verifyResult = await verifyResp.json();
      if (!verifyResp.ok || !verifyResult.success) throw new Error(verifyResult.message || 'Verification failed.');
      bootstrap.Modal.getInstance(document.querySelector('#completionModal'))?.hide();
      showFeedback('success', verifyResult.message);
      renderTransitions([]);
    };

    if (orderData.demo_mode) {
      // Demo mode — simulate payment instantly
      const demoPayId = 'pay_demo_' + Math.random().toString(36).slice(2, 14);
      const demoSig = 'demo_signature_' + Date.now();
      await verifyPayment(orderData.order_id, demoPayId, demoSig, 'demo');
    } else {
      // Live mode — Open Razorpay Checkout
      const options = {
        key: orderData.key,
        amount: orderData.amount,
        currency: orderData.currency,
        order_id: orderData.order_id,
        name: '<?= e(APP_NAME) ?>',
        description: 'Commission Payment - Referral #<?= (int) $referral['id'] ?>',
        prefill: { name: orderData.business_name, email: orderData.business_email },
        theme: { color: '#2457d6' },
        handler: async function(response) {
          try {
            await verifyPayment(response.razorpay_order_id, response.razorpay_payment_id, response.razorpay_signature, 'razorpay');
          } catch (err) {
            payFeedback.innerHTML = '<div class="alert alert-danger">' + err.message + '</div>';
          }
        },
        modal: { ondismiss: function() { payBtn.disabled = false; payBtn.innerHTML = '<i class="bi bi-credit-card"></i> Pay Commission'; } }
      };
      const rzp = new Razorpay(options);
      rzp.on('payment.failed', function(response) {
        payFeedback.innerHTML = '<div class="alert alert-danger">Payment failed: ' + (response.error?.description || 'Unknown error') + '</div>';
        payBtn.disabled = false; payBtn.innerHTML = '<i class="bi bi-credit-card"></i> Pay Commission';
      });
      rzp.open();
    }
  } catch (err) {
    payFeedback.innerHTML = '<div class="alert alert-danger">' + err.message + '</div>';
    payBtn.disabled = false; payBtn.innerHTML = '<i class="bi bi-credit-card"></i> Pay Commission';
  }
});

// Status update form (non-completion)
document.querySelectorAll('[data-referral-update]').forEach((form) => form.addEventListener('submit', async (event) => {
  event.preventDefault();
  const selectedOption = form.querySelector('select[name="status"] option:checked');
  if (selectedOption?.dataset.requiresCompletion === '1') {
    bootstrap.Modal.getOrCreateInstance(document.querySelector('#completionModal')).show();
    return;
  }
  if (!form.reportValidity()) return;
  const button = form.querySelector('[type="submit"]'); const original = button.textContent;
  button.disabled = true; button.textContent = 'Updating…';
  try {
    const endpoint = form.getAttribute('action') || window.location.href;
    const response = await fetch(endpoint, {method: 'POST', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body: new FormData(form)});
    const contentType = response.headers.get('content-type') || '';
    const body = await response.text();
    if (!contentType.includes('application/json')) throw new Error('The status service returned an invalid response. Please sign in again and retry.');
    let payload;
    try { payload = JSON.parse(body); } catch (_) { throw new Error('The status service returned invalid JSON.'); }
    if (!response.ok || !payload.success) throw new Error(payload.message || 'The referral could not be updated.');
    showFeedback('success', payload.message);
    renderTransitions(payload.allowed_transitions || []);
  } catch (error) { showFeedback('danger', error.message); button.disabled = false; button.textContent = original; }
}));
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
