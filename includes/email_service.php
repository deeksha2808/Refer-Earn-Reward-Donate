<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP transport selected exclusively through EMAIL_PROVIDER in .env.
 */
final class EmailService
{
    private const SUPPORTED_PROVIDERS = ['gmail', 'mailtrap', 'brevo'];

    /**
     * @param array<int, array{email:string, name?:string}|string> $cc
     * @param array<int, array{email:string, name?:string}|string> $bcc
     * @param array<int, array{path:string, name?:string}> $attachments
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody = '',
        array $cc = [],
        array $bcc = [],
        array $attachments = []
    ): void {
        $config = $this->configuration();
        $this->assertEmail($toEmail, 'Recipient email');

        try {
            $mail = $this->mailer($config);
            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($toEmail, $toName);
            $this->addRecipients($mail, $cc, 'cc');
            $this->addRecipients($mail, $bcc, 'bcc');
            $this->addAttachments($mail, $attachments);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody !== '' ? $textBody : trim(strip_tags($htmlBody));
            $mail->send();
        } catch (EmailServiceException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            app_log(sprintf(
                'Email delivery failed (provider=%s, recipient=%s): %s',
                $config['provider'],
                $toEmail,
                $this->safeError($exception->getMessage(), $config['password'])
            ));
            throw new EmailServiceException(
                'Email delivery failed using the ' . $config['provider'] . ' provider. Check the SMTP configuration and application log.',
                previous: $exception
            );
        }
    }

    public function provider(): string
    {
        return $this->configuration()['provider'];
    }

    /** @return array{provider:string,host:string,port:int,username:string,password:string,encryption:string,from_email:string,from_name:string} */
    private function configuration(): array
    {
        $this->loadPhpMailer();
        $provider = strtolower(trim((string) getenv('EMAIL_PROVIDER')));
        if (!in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            throw new EmailServiceException(
                'EMAIL_PROVIDER must be one of: gmail, mailtrap, or brevo.'
            );
        }

        $host = trim((string) getenv('SMTP_HOST'));
        $port = filter_var(getenv('SMTP_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        $username = (string) getenv('SMTP_USERNAME');
        $password = (string) getenv('SMTP_PASSWORD');
        $fromEmail = trim((string) getenv('SMTP_FROM_EMAIL'));
        $fromName = trim((string) getenv('SMTP_FROM_NAME')) ?: APP_NAME;

        if ($host === '' || $port === false || $username === '' || $password === '') {
            throw new EmailServiceException('SMTP configuration is incomplete. Set SMTP_HOST, SMTP_PORT, SMTP_USERNAME, and SMTP_PASSWORD.');
        }
        $this->assertEmail($fromEmail, 'SMTP_FROM_EMAIL');

        return [
            'provider' => $provider,
            'host' => $host,
            'port' => (int) $port,
            'username' => $username,
            'password' => $password,
            'encryption' => $this->encryption((string) getenv('SMTP_ENCRYPTION')),
            'from_email' => $fromEmail,
            'from_name' => $fromName,
        ];
    }

    /** @param array{host:string,port:int,username:string,password:string,encryption:string} $config */
    private function mailer(array $config): PHPMailer
    {
        $this->loadPhpMailer();

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->Port = $config['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        return $mail;
    }

    private function loadPhpMailer(): void
    {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (is_readable($autoload)) {
            require_once $autoload;
        }
        if (!class_exists(PHPMailer::class)) {
            throw new EmailServiceException('PHPMailer is not installed. Run composer install.');
        }
    }

    private function encryption(string $value): string
    {
        return match (strtolower(trim($value))) {
            '', 'tls', 'starttls' => PHPMailer::ENCRYPTION_STARTTLS,
            'ssl', 'smtps' => PHPMailer::ENCRYPTION_SMTPS,
            'none', 'off' => '',
            default => throw new EmailServiceException('SMTP_ENCRYPTION must be tls, ssl, or none.'),
        };
    }

    /** @param array<int, array{email:string, name?:string}|string> $recipients */
    private function addRecipients(PHPMailer $mail, array $recipients, string $type): void
    {
        foreach ($recipients as $recipient) {
            $email = is_array($recipient) ? (string) ($recipient['email'] ?? '') : (string) $recipient;
            $name = is_array($recipient) ? (string) ($recipient['name'] ?? '') : '';
            $this->assertEmail($email, strtoupper($type) . ' recipient email');
            $type === 'cc' ? $mail->addCC($email, $name) : $mail->addBCC($email, $name);
        }
    }

    /** @param array<int, array{path:string, name?:string}> $attachments */
    private function addAttachments(PHPMailer $mail, array $attachments): void
    {
        foreach ($attachments as $attachment) {
            $path = (string) ($attachment['path'] ?? '');
            if ($path === '' || !is_readable($path)) {
                throw new EmailServiceException('Email attachment is missing or unreadable.');
            }
            $mail->addAttachment($path, (string) ($attachment['name'] ?? ''));
        }
    }

    private function assertEmail(string $email, string $label): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new EmailServiceException($label . ' must be a valid email address.');
        }
    }

    private function safeError(string $message, string $password): string
    {
        $safe = $password !== '' ? str_replace($password, '[redacted]', $message) : $message;
        return preg_replace('/(password|pass|auth)\s*[:=]\s*[^\s,;]+/i', '$1=[redacted]', $safe) ?? 'SMTP error';
    }
}

final class EmailServiceException extends RuntimeException
{
}
