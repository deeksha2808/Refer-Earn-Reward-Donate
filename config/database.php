<?php
declare(strict_types=1);

/* Load a private local .env file for PHP's built-in development server. */
function load_local_environment(): void
{
    $envFile = dirname(__DIR__) . '/.env';
    if (!is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $name) || getenv($name) !== false) {
            continue;
        }
        putenv($name . '=' . trim($value, "\"'"));
    }
}

function app_log(string $message): void
{
    error_log('[refer-platform] ' . $message);
}

load_local_environment();

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'referral_platform');
define('DB_USER', getenv('DB_USER') ?: 'referral_user');
define('DB_PASS', (string) (getenv('DB_PASSWORD') === false ? '' : getenv('DB_PASSWORD')));
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (DB_PASS === '') {
        throw new RuntimeException('Database credentials are incomplete. Set DB_PASSWORD in the shell environment or a private .env file.');
    }

    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        app_log(sprintf('PDO connection failed for %s@%s/%s: %s', DB_USER, DB_HOST, DB_NAME, $exception->getMessage()));
        throw new RuntimeException('Unable to connect to MySQL. Verify DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, and that MySQL is running.', previous: $exception);
    }

    return $pdo;
}
