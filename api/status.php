<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
header('Cache-Control: no-store');
try {
    $session = ign_verified_session();
    ign_json(200, $session ? ['ok' => true, 'verified' => true, 'email' => $session['email']] : ['ok' => true, 'verified' => false]);
} catch (Throwable $error) {
    ign_json(200, ['ok' => true, 'verified' => false]);
}
