<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$rawToken = trim((string)($_GET['t'] ?? $_GET['token'] ?? ''));
$error = '';
try {
    if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) throw new RuntimeException('This verification link is not valid.');
    $db = ign_db();
    $statement = $db->prepare('SELECT email, verified_at, token_expires_at FROM subscribers WHERE token = ? LIMIT 1');
    $statement->execute([ign_hash($rawToken)]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('This verification link is invalid or has already been replaced.');
    if ((int)$row['token_expires_at'] < time()) throw new RuntimeException('This verification link has expired. Return to the card maker and request another one.');

    $email = (string)$row['email'];
    $wasVerified = !empty($row['verified_at']);
    $verifiedAt = $wasVerified ? (string)$row['verified_at'] : gmdate('c');
    $replacementToken = ign_hash(bin2hex(random_bytes(32)));
    $db->prepare('UPDATE subscribers SET verified_at = ?, updated_at = ?, token = ?, token_expires_at = 0 WHERE email = ?')
       ->execute([$verifiedAt, gmdate('c'), $replacementToken, $email]);

    if (!$wasVerified) {
        ign_send_mail(IGN_NOTIFY_EMAIL, 'New verified InvoiceGeneratorNow signup', "Email: {$email}\nVerified: {$verifiedAt}\n");
    }

    $rawSession = bin2hex(random_bytes(32));
    $expiresAt = time() + IGN_SESSION_DAYS * 86400;
    $db->prepare('DELETE FROM access_sessions WHERE email = ? OR expires_at < ?')->execute([$email, time()]);
    $db->prepare('INSERT INTO access_sessions(email,session_hash,created_at,expires_at) VALUES(?,?,?,?)')
       ->execute([$email, ign_hash($rawSession), time(), $expiresAt]);
    ign_set_session_cookie($rawSession, $expiresAt);
    header('Location: /?verified=1', true, 303);
    exit;
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}
?><!doctype html><html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Email verification | InvoiceGeneratorNow</title><style>body{font:18px/1.6 system-ui;margin:0;background:#f8f4ec;color:#18212b}.box{max-width:680px;margin:10vh auto;padding:34px;background:#fff;border-radius:24px;box-shadow:0 20px 60px #0002}.button{display:inline-block;padding:12px 20px;border-radius:99px;background:#6f3048;color:#fff;text-decoration:none;font-weight:800}</style></head><body><main class="box"><h1>We could not verify that email</h1><p><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></p><p><a class="button" href="/">Return to the card maker</a></p></main></body></html>
