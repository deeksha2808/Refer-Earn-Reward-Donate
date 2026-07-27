# Email Provider Guide

The application selects its SMTP provider exclusively from `EMAIL_PROVIDER` in the private `.env` file. Application code does not need to change when switching providers.

## Required `.env` variables

```dotenv
EMAIL_PROVIDER=mailtrap
SMTP_HOST=
SMTP_PORT=
SMTP_USERNAME=
SMTP_PASSWORD=
SMTP_ENCRYPTION=tls
SMTP_FROM_EMAIL=
SMTP_FROM_NAME=
```

`EMAIL_PROVIDER` must be exactly `mailtrap` or `brevo`. `SMTP_ENCRYPTION` accepts `tls` (or `starttls`), `ssl` (or `smtps`), and `none` only where the provider explicitly permits an unencrypted connection.

## Mailtrap (development)

Mailtrap Email Sandbox receives messages in a safe testing inbox instead of delivering them to real recipients. Create or open a Mailtrap sandbox inbox, copy its SMTP host, port, username, and password, then configure:

```dotenv
EMAIL_PROVIDER=mailtrap
SMTP_HOST=sandbox.smtp.mailtrap.io
SMTP_PORT=587
SMTP_USERNAME=your-mailtrap-username
SMTP_PASSWORD=your-mailtrap-password
SMTP_ENCRYPTION=tls
SMTP_FROM_EMAIL=no-reply@your-development-domain.test
SMTP_FROM_NAME="Refer Earn Bill Reward and Donate"
```

Use the exact host and credentials displayed by your Mailtrap inbox; they may differ by account or region.

## Brevo (production)

Brevo delivers production email through its SMTP relay. Verify the sending domain or sender address in Brevo first, then configure the SMTP relay values from the Brevo dashboard:

```dotenv
EMAIL_PROVIDER=brevo
SMTP_HOST=smtp-relay.brevo.com
SMTP_PORT=587
SMTP_USERNAME=your-brevo-smtp-login
SMTP_PASSWORD=your-brevo-smtp-key
SMTP_ENCRYPTION=tls
SMTP_FROM_EMAIL=no-reply@your-verified-domain.example
SMTP_FROM_NAME="Refer Earn Bill Reward and Donate"
```

## Switching providers

1. Update the eight variables above in the private `.env` file.
2. Set `EMAIL_PROVIDER=mailtrap` for development or `EMAIL_PROVIDER=brevo` for production.
3. Restart PHP-FPM, Apache, or the PHP development server if it keeps environment values in memory.
4. Open `test_email.php`, enter a recipient address, and send a test message.

No notification, referral, wallet, authentication, or business code changes are needed.

## Production recommendations

- Keep `.env` private and never commit SMTP credentials.
- Use a verified Brevo sender/domain with SPF, DKIM, and DMARC configured.
- Use TLS and a dedicated SMTP key; rotate it if exposed.
- Restrict or remove `test_email.php` in production after verification.
- Monitor application logs for sanitized delivery failures; SMTP passwords are never written by `EmailService`.
