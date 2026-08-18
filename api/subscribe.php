<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if (($_GET['config'] ?? '') === '1') {
    ign_json(200, [
        'ok' => true,
        'freeUses' => IGN_FREE_USES,
        'minSeconds' => IGN_MIN_SUBMIT_SECONDS,
        'configured' => IGN_SECRETS_PRESENT,
    ]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') ign_json(405, ['ok' => false, 'message' => 'Method not allowed.']);
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && !in_array($origin, [IGN_SITE_URL, 'https://www.invoicegeneratornow.com'], true)) {
    ign_json(403, ['ok' => false, 'message' => 'This sign-up request was not accepted.']);
}
if (!IGN_SECRETS_PRESENT) {
    ign_json(503, ['ok' => false, 'message' => 'Email verification is not configured yet. Add the private Hostinger SMTP settings, then try again.']);
}

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if (str_contains($contentType, 'application/json')) {
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) ign_json(400, ['ok' => false, 'message' => 'Please try again.']);
} else {
    $data = $_POST;
}

$email = strtolower(trim((string)($data['email'] ?? '')));
$honeypot = trim((string)($data['website'] ?? $data['company'] ?? ''));
$timestampMs = (int)($data['ts'] ?? $data['started'] ?? 0);
$page = substr(trim((string)($data['page'] ?? '')), 0, 180);

/* Bots frequently fill hidden fields. Return a convincing fake success. */
if ($honeypot !== '') ign_json(200, ['ok' => true, 'pending' => true]);

$elapsedMs = (int)(microtime(true) * 1000) - $timestampMs;
if ($timestampMs <= 0 || $elapsedMs < IGN_MIN_SUBMIT_SECONDS * 1000) {
    ign_json(429, ['ok' => false, 'message' => 'Please take a few seconds to complete the form, then try again.', 'reason' => 'time_trap', 'elapsed_ms' => $elapsedMs]);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    ign_json(422, ['ok' => false, 'message' => 'Please enter a valid email address.']);
}

$domain = substr(strrchr($email, '@') ?: '', 1);
if (function_exists('checkdnsrr') && !checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
    ign_json(422, ['ok' => false, 'message' => 'That email domain does not appear to exist. Please check it.']);
}

try {
    $db = ign_db();
    $ipHash = ign_ip_hash();
    $now = time();
    $attemptQuery = $db->prepare('SELECT count, window_start FROM attempts WHERE ip_hash = ?');
    $attemptQuery->execute([$ipHash]);
    $attempt = $attemptQuery->fetch(PDO::FETCH_ASSOC);
    if ($attempt && $now - (int)$attempt['window_start'] < 3600 && (int)$attempt['count'] >= IGN_RATE_LIMIT_PER_HOUR) {
        ign_json(429, ['ok' => false, 'message' => 'Too many verification requests from this connection. Please try again later.']);
    }
    if (!$attempt || $now - (int)$attempt['window_start'] >= 3600) {
        $db->prepare('INSERT OR REPLACE INTO attempts(ip_hash,count,window_start) VALUES(?,?,?)')->execute([$ipHash, 1, $now]);
    } else {
        $db->prepare('UPDATE attempts SET count = count + 1 WHERE ip_hash = ?')->execute([$ipHash]);
    }

    $verificationRaw = bin2hex(random_bytes(32));
    $unsubscribeRaw = bin2hex(random_bytes(32));
    $expires = $now + IGN_VERIFY_HOURS * 3600;
    $iso = gmdate('c');
    $statement = $db->prepare('INSERT INTO subscribers(email,token,page,created_at,updated_at,ip_hash,verified_at,token_expires_at,unsubscribe_token)
        VALUES(?,?,?,?,?,?,?,?,?)
        ON CONFLICT(email) DO UPDATE SET token=excluded.token,page=excluded.page,updated_at=excluded.updated_at,ip_hash=excluded.ip_hash,token_expires_at=excluded.token_expires_at,unsubscribe_token=excluded.unsubscribe_token');
    $statement->execute([$email, ign_hash($verificationRaw), $page, $iso, $iso, $ipHash, null, $expires, ign_hash($unsubscribeRaw)]);

    $verifyUrl = ign_https_url('/api/verify.php?t=' . rawurlencode($verificationRaw));
    $unsubscribeUrl = ign_https_url('/api/unsubscribe.php?email=' . rawurlencode($email) . '&token=' . rawurlencode($unsubscribeRaw));
    $subject = 'Confirm your email address';
    $body = "Hello,\n\n"
        . "Open the private link below to confirm your email and unlock unlimited InvoiceGeneratorNow use on this browser:\n\n"
        . $verifyUrl . "\n\n"
        . "The link works for " . IGN_VERIFY_HOURS . " hours. Nothing is charged and no payment-card details are requested.\n\n"
        . "If you did not request this email, ignore it and nothing will happen.\n\n"
        . "Delete this address from our records:\n" . $unsubscribeUrl . "\n\n"
        . "InvoiceGeneratorNow\n" . IGN_SITE_URL . "\n";
    if (!ign_send_mail($email, $subject, $body)) {
        ign_json(503, ['ok' => false, 'message' => 'The verification email could not be sent. Please check the private Hostinger SMTP settings and try again.']);
    }
    ign_json(200, ['ok' => true, 'pending' => true, 'message' => 'Check your inbox and open the verification link. Unlimited access unlocks only after verification.']);
} catch (Throwable $error) {
    error_log('InvoiceGeneratorNow signup error: ' . $error->getMessage());
    ign_json(500, ['ok' => false, 'message' => 'The sign-up service is temporarily unavailable. Please try again shortly.']);
}
