<?php
declare(strict_types=1);
require_once __DIR__ . '/smtp_mailer.php';

const IGN_COOKIE_NAME = 'ign_verified_access';
const IGN_VERIFY_HOURS = 48;
const IGN_SESSION_DAYS = 365;
const IGN_SITE_URL = 'https://invoicegeneratornow.com';
const IGN_FREE_USES = 3;
const IGN_MIN_SUBMIT_SECONDS = 3;
const IGN_RATE_LIMIT_PER_HOUR = 3;

$IGN_SECRET_PATHS = [
    dirname(__DIR__, 2) . '/ign_private/secrets.php',
    dirname(__DIR__) . '/../ign_private/secrets.php',
];
$IGN_SECRETS = [
    'SITE_SALT' => '',
    'NOTIFY_EMAIL' => 'info@invoicegeneratornow.com',
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => 465,
    'smtp_user' => 'info@invoicegeneratornow.com',
    'smtp_pass' => '',
    'from_email' => 'info@invoicegeneratornow.com',
    'from_name' => 'InvoiceGeneratorNow',
];
foreach ($IGN_SECRET_PATHS as $secretPath) {
    if (!is_readable($secretPath)) continue;
    $loaded = require $secretPath;
    if (is_array($loaded)) $IGN_SECRETS = array_merge($IGN_SECRETS, $loaded);
    break;
}
define('IGN_SITE_SALT', (string)$IGN_SECRETS['SITE_SALT']);
define('IGN_NOTIFY_EMAIL', (string)$IGN_SECRETS['NOTIFY_EMAIL']);
define('IGN_SECRETS_PRESENT', IGN_SITE_SALT !== '' && (string)$IGN_SECRETS['smtp_pass'] !== '');

function ign_json(int $status, array $payload): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function ign_private_dir(): string {
    $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'ign_private';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create private storage.');
    }
    if (!is_writable($dir)) throw new RuntimeException('Private storage is not writable.');
    return $dir;
}

function ign_db(): PDO {
    static $db = null;
    if ($db instanceof PDO) return $db;
    $db = new PDO('sqlite:' . ign_private_dir() . DIRECTORY_SEPARATOR . 'leads.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('CREATE TABLE IF NOT EXISTS subscribers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        token TEXT NOT NULL,
        page TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        ip_hash TEXT,
        verified_at TEXT,
        token_expires_at INTEGER,
        unsubscribe_token TEXT
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS attempts (ip_hash TEXT PRIMARY KEY, count INTEGER NOT NULL, window_start INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS access_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL,
        session_hash TEXT NOT NULL UNIQUE,
        created_at INTEGER NOT NULL,
        expires_at INTEGER NOT NULL
    )');
    return $db;
}

function ign_https_url(string $path): string {
    return IGN_SITE_URL . '/' . ltrim($path, '/');
}

function ign_hash(string $value): string {
    return hash('sha256', $value);
}

function ign_ip_hash(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return ign_hash($ip . '|' . IGN_SITE_SALT);
}

function ign_set_session_cookie(string $rawToken, int $expiresAt): void {
    setcookie(IGN_COOKIE_NAME, $rawToken, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function ign_clear_session_cookie(): void {
    setcookie(IGN_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function ign_verified_session(): ?array {
    $raw = (string)($_COOKIE[IGN_COOKIE_NAME] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $raw)) return null;
    $db = ign_db();
    $stmt = $db->prepare('SELECT email, expires_at FROM access_sessions WHERE session_hash = ? LIMIT 1');
    $stmt->execute([ign_hash($raw)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['expires_at'] < time()) return null;
    return $row;
}

function ign_send_mail(string $to, string $subject, string $body): bool {
    global $IGN_SECRETS;
    if (!IGN_SECRETS_PRESENT) return false;
    $config = [
        'host' => $IGN_SECRETS['smtp_host'],
        'port' => $IGN_SECRETS['smtp_port'],
        'user' => $IGN_SECRETS['smtp_user'],
        'pass' => $IGN_SECRETS['smtp_pass'],
        'from' => $IGN_SECRETS['from_email'],
        'from_name' => $IGN_SECRETS['from_name'],
    ];
    $error = null;
    $sent = ign_smtp_send($config, $to, $subject, $body, $error);
    if (!$sent) error_log('InvoiceGeneratorNow SMTP error: ' . (string)$error);
    return $sent;
}
