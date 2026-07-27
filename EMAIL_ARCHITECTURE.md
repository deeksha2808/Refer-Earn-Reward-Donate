# Email Architecture

## EmailService

`includes/email_service.php` contains the reusable `EmailService` transport layer. It validates `EMAIL_PROVIDER`, loads the SMTP settings at send time, configures PHPMailer, and sends HTML email with a plain-text fallback.

Its `send()` method also supports CC, BCC, and file attachments:

```php
(new EmailService())->send(
    'recipient@example.com',
    'Recipient Name',
    'Subject',
    '<p>HTML body</p>',
    'Plain text body',
    [['email' => 'cc@example.com', 'name' => 'CC Recipient']],
    ['audit@example.com'],
    [['path' => '/safe/path/invoice.pdf', 'name' => 'invoice.pdf']]
);
```

## Provider selection flow

```text
.env EMAIL_PROVIDER
        |
        +-- mailtrap --> SMTP_HOST / SMTP_PORT / SMTP_USERNAME / SMTP_PASSWORD
        |
        +-- brevo -----> SMTP_HOST / SMTP_PORT / SMTP_USERNAME / SMTP_PASSWORD
                                |
                                v
                         EmailService -> PHPMailer -> SMTP provider
```

The providers intentionally share the same SMTP variable names. `EmailService` accepts only `mailtrap` and `brevo`; an unset or unsupported value produces a clear configuration exception. No provider host, credentials, sender, or port is hardcoded.

## Notification integration

`NotificationService` remains the notification business layer. It still creates the same in-app records and exposes the same event methods for opportunity creation, referral submission, referral status changes, referral completion, and wallet crediting. Only its private `sendEmail()` method now delegates message delivery to `EmailService`.

## Error handling and logging

Invalid provider, missing SMTP settings, malformed addresses, invalid encryption, and unreadable attachments raise `EmailServiceException` with actionable messages. PHPMailer transport failures are logged with the selected provider and recipient, then rethrown as a generic safe exception. SMTP debug output is disabled by default, including in `test_email.php`.

## Security considerations

SMTP credentials stay only in `.env`. `EmailService` never logs the configured password and sanitizes password-like values in SMTP error output. Use TLS, verified sender domains, least-privilege SMTP keys, and protected attachment paths. The test page should be restricted or removed from public production access after setup verification.
