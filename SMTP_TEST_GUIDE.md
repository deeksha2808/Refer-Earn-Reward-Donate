# SMTP Test Guide

## What is configured

The temporary [test_email.php](test_email.php) page reads the application's `.env` file through `config/database.php` and uses Composer's PHPMailer installation. It expects this Brevo configuration:

```dotenv
SMTP_ENABLED=true
SMTP_HOST=smtp-relay.brevo.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=your-brevo-smtp-username
SMTP_PASSWORD=your-brevo-smtp-key
SMTP_FROM=your-verified-sender@example.com
SMTP_FROM_NAME=Refer Earn Bill Reward and Donate
```

`SMTP_FROM_EMAIL` is also accepted by the temporary page for compatibility. Do not put a real SMTP password in this guide, source code, or version control.

## Test steps

1. Confirm the PHP development server is running at `http://127.0.0.1:8000/`.
2. Open `http://127.0.0.1:8000/test_email.php`.
3. Confirm the page displays `smtp-relay.brevo.com`, `587`, and `tls` in the configuration summary.
4. Enter an email address you control and select **Send test email**.
5. A successful request displays `Email sent successfully`; also check the recipient inbox and spam folder.
6. On failure, the page shows the exact PHPMailer/SMTP error. Correct the corresponding `.env` value and retry. The page deliberately leaves SMTP debug mode off, so the password is not output or logged.

## Remove the temporary page

After verification, delete `test_email.php` from the project root and keep this guide only if you want a record of the procedure. The test page does not change referral, wallet, report, or notification behavior.

## Note for the existing notification service

The current notification service reads `SMTP_FROM_EMAIL`, while the provided local `.env` uses `SMTP_FROM`. This temporary page accepts both values. Before enabling email delivery for the existing notification feature, add the same verified sender as `SMTP_FROM_EMAIL` to `.env`, or make a separately approved configuration update.
