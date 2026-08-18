<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Content-Security-Policy: default-src \'self\'; style-src \'self\' \'unsafe-inline\'; form-action \'self\'; frame-ancestors \'none\'; base-uri \'none\'');

session_name('ign_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/api/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$adminPassword = (string)($IGN_SECRETS['ADMIN_PASSWORD'] ?? $IGN_SECRETS['admin_password'] ?? '');
$configured = IGN_SECRETS_PRESENT && $adminPassword !== '';

function ign_admin_csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return (string)$_SESSION['csrf'];
}
function ign_admin_is_logged_in(): bool {
    return !empty($_SESSION['ign_admin_authenticated']);
}
function ign_admin_safe_return(): never {
    header('Location: /api/leads.php', true, 303);
    exit;
}
function ign_admin_escape(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$notice = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'login') {
        $submitted = (string)($_POST['password'] ?? '');
        if (!$configured) {
            $error = 'Admin access is not configured. Add admin_password to ign_private/secrets.php.';
        } elseif (hash_equals($adminPassword, $submitted)) {
            session_regenerate_id(true);
            $_SESSION['ign_admin_authenticated'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(24));
            ign_admin_safe_return();
        } else {
            usleep(350000);
            $error = 'The admin password is incorrect.';
        }
    } elseif ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
        ign_admin_safe_return();
    } elseif (ign_admin_is_logged_in()) {
        $csrf = (string)($_POST['csrf'] ?? '');
        if (!hash_equals(ign_admin_csrf(), $csrf)) {
            http_response_code(403);
            $error = 'The request expired. Refresh the page and try again.';
        } elseif ($action === 'delete') {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'That subscriber address is not valid.';
            } else {
                try {
                    $db = ign_db();
                    $db->beginTransaction();
                    $db->prepare('DELETE FROM access_sessions WHERE email = ?')->execute([$email]);
                    $stmt = $db->prepare('DELETE FROM subscribers WHERE email = ?');
                    $stmt->execute([$email]);
                    $db->commit();
                    $_SESSION['notice'] = $stmt->rowCount() ? 'Subscriber deleted permanently.' : 'Subscriber was already absent.';
                    ign_admin_safe_return();
                } catch (Throwable $e) {
                    if (isset($db) && $db->inTransaction()) $db->rollBack();
                    $error = 'The subscriber could not be deleted.';
                    error_log('CMM admin delete error: ' . $e->getMessage());
                }
            }
        }
    }
}

if (!empty($_SESSION['notice'])) {
    $notice = (string)$_SESSION['notice'];
    unset($_SESSION['notice']);
}

if (ign_admin_is_logged_in() && (string)($_GET['download'] ?? '') === 'verified-csv') {
    try {
        $db = ign_db();
        $stmt = $db->query("SELECT email, page, verified_at FROM subscribers WHERE verified_at IS NOT NULL AND verified_at <> '' ORDER BY verified_at DESC");
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="card-maker-messages-verified-subscribers-' . gmdate('Y-m-d') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['Email', 'Source page', 'Verified at (UTC)']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [(string)$row['email'], (string)$row['page'], (string)$row['verified_at']]);
        }
        fclose($out);
        exit;
    } catch (Throwable $e) {
        $error = 'The CSV could not be created.';
        error_log('CMM admin CSV error: ' . $e->getMessage());
    }
}

$stats = ['total' => 0, 'verified' => 0, 'pending' => 0, 'today' => 0];
$rows = [];
if (ign_admin_is_logged_in()) {
    try {
        $db = ign_db();
        $stats['total'] = (int)$db->query('SELECT COUNT(*) FROM subscribers')->fetchColumn();
        $stats['verified'] = (int)$db->query("SELECT COUNT(*) FROM subscribers WHERE verified_at IS NOT NULL AND verified_at <> ''")->fetchColumn();
        $stats['pending'] = $stats['total'] - $stats['verified'];
        $todayStart = gmdate('Y-m-d') . 'T00:00:00';
        $todayStmt = $db->prepare("SELECT COUNT(*) FROM subscribers WHERE verified_at IS NOT NULL AND verified_at >= ?");
        $todayStmt->execute([$todayStart]);
        $stats['today'] = (int)$todayStmt->fetchColumn();
        $rows = $db->query("SELECT email, page, created_at, verified_at FROM subscribers ORDER BY COALESCE(verified_at, created_at) DESC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $error = 'The subscriber database could not be opened.';
        error_log('CMM admin database error: ' . $e->getMessage());
    }
}
?><!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Subscribers | InvoiceGeneratorNow</title>
<style>
:root{--ink:#18212b;--muted:#667085;--paper:#f7f4ee;--panel:#fffdf9;--line:#ddd3c7;--brand:#73304d;--brand2:#8d3d60;--good:#16794b;--pending:#a05b00;--danger:#bb3e35}
*{box-sizing:border-box}body{margin:0;background:var(--paper);color:var(--ink);font:16px/1.5 system-ui,-apple-system,Segoe UI,sans-serif}.wrap{width:min(1120px,calc(100% - 28px));margin:48px auto}.top{display:flex;gap:18px;align-items:center;justify-content:space-between;flex-wrap:wrap}.brand{font-family:Georgia,serif;font-size:clamp(30px,5vw,48px);margin:0}.sub{color:var(--muted);margin:.35rem 0 0}.card{background:var(--panel);border:1px solid var(--line);border-radius:22px;padding:24px;box-shadow:0 16px 45px #44322612}.login{max-width:520px;margin:11vh auto}.field{display:grid;gap:7px;margin:20px 0}.field input{width:100%;min-height:48px;border:1px solid var(--line);border-radius:12px;padding:11px 13px;font:inherit}.button{display:inline-flex;min-height:44px;align-items:center;justify-content:center;border:0;border-radius:999px;padding:10px 18px;background:var(--brand);color:#fff;font-weight:800;text-decoration:none;cursor:pointer}.button.secondary{background:#fff;color:var(--brand);border:1px solid var(--line)}.button.danger{background:var(--danger);padding:6px 12px;min-height:36px}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:24px 0}.stat{background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:18px}.stat strong{font:700 34px/1 Georgia,serif;color:var(--brand)}.stat span{display:block;color:var(--muted);margin-top:7px}.message{padding:12px 14px;border-radius:12px;margin:16px 0;background:#f2e4ea;color:#5b1f38;font-weight:700}.message.good{background:#e8f4ed;color:#125d3b}.toolbar{display:flex;gap:10px;align-items:center;justify-content:flex-end;flex-wrap:wrap}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:18px;background:var(--panel)}table{width:100%;border-collapse:collapse;min-width:780px}th,td{text-align:left;padding:13px 14px;border-bottom:1px solid #ebe3da;vertical-align:top}th{background:#263747;color:#fff;font-size:13px;text-transform:uppercase;letter-spacing:.04em}tr:last-child td{border-bottom:0}.status{font-weight:800}.status.verified{color:var(--good)}.status.pending{color:var(--pending)}.email{font-weight:700}.source{color:var(--muted);max-width:260px;overflow-wrap:anywhere}.small{color:var(--muted);font-size:14px}@media(max-width:760px){.wrap{margin:24px auto}.stats{grid-template-columns:1fr 1fr}.toolbar{justify-content:flex-start}.card{padding:18px}}@media(max-width:420px){.stats{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php if (!ign_admin_is_logged_in()): ?>
<main class="wrap login">
<section class="card">
<p class="small">PRIVATE ADMIN</p>
<h1 class="brand">Subscriber login</h1>
<p class="sub">View verified and pending InvoiceGeneratorNow sign-ups.</p>
<?php if ($error): ?><div class="message"><?=ign_admin_escape($error)?></div><?php endif; ?>
<?php if (!$configured): ?><div class="message">Add <code>'admin_password' =&gt; 'YOUR PASSWORD'</code> to <code>ign_private/secrets.php</code>. The dashboard stays locked until it is configured.</div><?php endif; ?>
<form method="post" autocomplete="off">
<input type="hidden" name="action" value="login">
<label class="field"><strong>Admin password</strong><input type="password" name="password" required autocomplete="current-password"></label>
<button class="button" type="submit">Sign in</button>
</form>
</section>
</main>
<?php else: ?>
<main class="wrap">
<header class="top">
<div><p class="small">INVOICEGENERATORNOW ADMIN</p><h1 class="brand">Subscribers</h1><p class="sub">Only verified subscribers are included in the CSV export.</p></div>
<div class="toolbar"><a class="button" href="?download=verified-csv">Download verified CSV</a><form method="post"><input type="hidden" name="action" value="logout"><button class="button secondary" type="submit">Sign out</button></form></div>
</header>
<?php if ($error): ?><div class="message"><?=ign_admin_escape($error)?></div><?php endif; ?>
<?php if ($notice): ?><div class="message good"><?=ign_admin_escape($notice)?></div><?php endif; ?>
<section class="stats" aria-label="Subscriber totals">
<div class="stat"><strong><?=$stats['verified']?></strong><span>Verified emails</span></div>
<div class="stat"><strong><?=$stats['pending']?></strong><span>Pending verification</span></div>
<div class="stat"><strong><?=$stats['total']?></strong><span>Total submitted</span></div>
<div class="stat"><strong><?=$stats['today']?></strong><span>Verified today</span></div>
</section>
<section class="table-wrap">
<table>
<thead><tr><th>Email</th><th>Status</th><th>Source page</th><th>Date (UTC)</th><th>Action</th></tr></thead>
<tbody>
<?php if (!$rows): ?><tr><td colspan="5">No subscribers have been recorded yet.</td></tr><?php endif; ?>
<?php foreach ($rows as $row): $verified = !empty($row['verified_at']); ?>
<tr>
<td class="email"><?=ign_admin_escape($row['email'])?></td>
<td><span class="status <?=$verified?'verified':'pending'?>"><?=$verified?'Verified':'Pending'?></span></td>
<td class="source"><?=ign_admin_escape($row['page'] ?: '/')?></td>
<td><?=ign_admin_escape($verified ? $row['verified_at'] : $row['created_at'])?></td>
<td><form method="post" onsubmit="return confirm('Delete this subscriber permanently?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="csrf" value="<?=ign_admin_escape(ign_admin_csrf())?>"><input type="hidden" name="email" value="<?=ign_admin_escape($row['email'])?>"><button class="button danger" type="submit">Delete</button></form></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>
</main>
<?php endif; ?>
</body>
</html>
