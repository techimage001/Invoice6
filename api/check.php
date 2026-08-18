<?php
declare(strict_types=1);
/* TEMPORARY. Visit /api/check.php  then DELETE this file.
   Add ?to=you@gmail.com to attempt a real send. */
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
global $IGN_SECRETS;

echo "API CHECK\n=========\n\n";

echo "1. SECRETS FILE\n";
foreach ([dirname(__DIR__, 2) . '/ign_private/secrets.php', dirname(__DIR__) . '/../ign_private/secrets.php'] as $p) {
    printf("   %-8s %s\n", is_readable($p) ? 'FOUND' : 'missing', $p);
}

echo "\n2. VALUES THE API IS USING\n";
printf("   SITE_SALT   : %s   %s\n", IGN_SITE_SALT !== '' ? 'set ('.strlen(IGN_SITE_SALT).' chars)' : '(EMPTY)', IGN_SITE_SALT !== '' ? 'OK' : '<<< blocks everything');
printf("   smtp_host   : %s\n", (string)$IGN_SECRETS['smtp_host']);
printf("   smtp_port   : %s\n", (string)$IGN_SECRETS['smtp_port']);
printf("   smtp_user   : %s\n", (string)$IGN_SECRETS['smtp_user']);
printf("   smtp_pass   : %s   %s\n", $IGN_SECRETS['smtp_pass'] !== '' ? 'set ('.strlen((string)$IGN_SECRETS['smtp_pass']).' chars)' : '(EMPTY)', $IGN_SECRETS['smtp_pass'] !== '' ? 'OK' : '<<< blocks everything');
printf("   from_email  : %s\n", (string)$IGN_SECRETS['from_email']);
printf("   notify      : %s\n", IGN_NOTIFY_EMAIL);
printf("   CONFIGURED  : %s\n", IGN_SECRETS_PRESENT ? 'YES' : 'NO  <<< subscribe.php will refuse with 503');

echo "\n3. DATABASE\n";
try { $dir = ign_private_dir(); printf("   dir writable: %s\n   file        : %s\n", is_writable($dir) ? 'yes' : 'NO', $dir . '/leads.sqlite'); }
catch (Throwable $e) { echo '   FAIL: ' . $e->getMessage() . "\n"; }
try { $db = ign_db(); $n = (int)$db->query('SELECT COUNT(*) FROM subscribers')->fetchColumn(); echo "   subscribers rows: $n\n"; }
catch (Throwable $e) { echo '   DB ERROR: ' . $e->getMessage() . "\n"; }

echo "\n4. REAL SEND TEST\n";
$to = (string)($_GET['to'] ?? IGN_NOTIFY_EMAIL);
if (!IGN_SECRETS_PRESENT) { echo "   skipped, not configured\n"; }
else {
    $err = null;
    $cfg = ['host'=>$IGN_SECRETS['smtp_host'],'port'=>$IGN_SECRETS['smtp_port'],'user'=>$IGN_SECRETS['smtp_user'],
            'pass'=>$IGN_SECRETS['smtp_pass'],'from'=>$IGN_SECRETS['from_email'],'from_name'=>$IGN_SECRETS['from_name']];
    $ok = ign_smtp_send($cfg, $to, 'API check from InvoiceGeneratorNow', "Outbound mail works.\n\nSent " . gmdate('c') . "\n", $err);
    printf("   to %s : %s\n", $to, $ok ? 'SENT' : 'FAILED');
    if (!$ok) echo '   error: ' . (string)$err . "\n";
}

echo "\n5. RECENT SUBSCRIBERS\n";
try {
    $rows = ign_db()->query('SELECT email, created_at, verified_at FROM subscribers ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) echo "   none yet. If someone submitted the form and nothing is here, the insert failed.\n";
    foreach ($rows as $r) printf("   %s  %s  %s\n", $r['email'], $r['created_at'], $r['verified_at'] ? 'VERIFIED' : 'pending');
} catch (Throwable $e) { echo '   ' . $e->getMessage() . "\n"; }

echo "\nDELETE THIS FILE when finished.\n";
