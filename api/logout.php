<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
header('Cache-Control: no-store');
try {
    $raw = (string)($_COOKIE[IGN_COOKIE_NAME] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/', $raw)) ign_db()->prepare('DELETE FROM access_sessions WHERE session_hash = ?')->execute([ign_hash($raw)]);
} catch (Throwable $error) {
    // Local sign-out must still succeed even if storage is temporarily unavailable.
}
ign_clear_session_cookie();
ign_json(200, ['ok' => true]);
