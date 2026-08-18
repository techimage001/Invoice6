<?php
// Copy/edit values as needed after deployment. Keep this file OUTSIDE public_html.
// Load secrets from OUTSIDE the web root so deployments can never delete them.
// Two file formats are supported, matching the rest of the network:
//   1) return ['smtp_host'=>'...', 'smtp_pass'=>'...'];   (preferred, always works)
//   2) putenv('SMTP_HOST=...');                            (legacy)
$__home = getenv('HOME') ?: dirname(dirname(dirname(__DIR__)));
$__siteRoot = dirname(dirname(__DIR__));            // domains/<site>/  (sibling of public_html)
$__secretPaths = [
    $__siteRoot . '/ign_private/secrets.php',
    $__siteRoot . '/private/secrets.php',
    $__home . '/ign_private/secrets.php',
    $__home . '/private/secrets.php',
    $__home . '/secrets.php',
    __DIR__ . '/secrets.php',
];
$__secrets = [];
foreach ($__secretPaths as $__s) {
    if (is_file($__s)) {
        $__r = require $__s;                        // array format returns here
        if (is_array($__r)) $__secrets = $__r;       // putenv format returns 1 and is already applied
        define('SECRETS_LOADED_FROM', $__s);
        break;
    }
}
if (!defined('SECRETS_LOADED_FROM')) define('SECRETS_LOADED_FROM', '');

/* Read a setting from the array file first, then the environment, then a default.
   Accepts the naming used across the network (smtp_host) and the uppercase form. */
function ign_secret(string $key, $default = null) {
    global $__secrets;
    $lower = strtolower($key);
    if (isset($__secrets[$lower]) && $__secrets[$lower] !== '') return $__secrets[$lower];
    if (isset($__secrets[$key]) && $__secrets[$key] !== '') return $__secrets[$key];
    $env = getenv(strtoupper($key));
    if ($env !== false && $env !== '') return $env;
    return $default;
}
define('APP_NAME', 'InvoiceGeneratorNow');
define('APP_URL', rtrim(ign_secret('app_url','https://InvoiceGeneratorNow.com'),'/'));
define('CONTACT_EMAIL', ign_secret('contact_email', ign_secret('from_email','info@InvoiceGeneratorNow.com')));
define('SITE_SALT', ign_secret('site_salt', hash('sha256', 'invoicegeneratornow|ign-unsubscribe-salt')));

/* The database MUST live outside public_html. Every Git deploy replaces public_html,
   which silently destroyed the database (accounts, subscribers and mail logs) on every
   release. These candidates are tried in order; the first writable one wins, and an
   existing database is always preferred over creating a new one. */
$__dbEnv = ign_secret('db_path');
if ($__dbEnv) {
    define('DB_PATH', $__dbEnv);
} else {
    $__siteRoot = dirname(dirname(__DIR__));           // domains/<site>/
    $__homeDir  = getenv('HOME') ?: dirname($__siteRoot);
    $__dbCandidates = [
        (SECRETS_LOADED_FROM ? dirname(SECRETS_LOADED_FROM).'/app.sqlite' : $__siteRoot.'/ign_private/app.sqlite'),  // beside secrets.php, exactly like the rest of the network
        $__siteRoot . '/ign_private/app.sqlite',
        $__homeDir  . '/ign_private/app.sqlite',
        $__siteRoot . '/private/app.sqlite',
        dirname(__DIR__) . '/storage/app.sqlite',       // legacy, inside public_html
    ];
    $__chosen = null;
    foreach ($__dbCandidates as $__c) { if (is_file($__c)) { $__chosen = $__c; break; } }
    if (!$__chosen) {
        foreach ($__dbCandidates as $__c) {
            $__dir = dirname($__c);
            if (is_dir($__dir) && is_writable($__dir)) { $__chosen = $__c; break; }
            if (!is_dir($__dir) && @mkdir($__dir, 0755, true)) { $__chosen = $__c; break; }
        }
    }
    define('DB_PATH', $__chosen ?: dirname(__DIR__) . '/storage/app.sqlite');
}
define('ADMIN_EMAIL', ign_secret('admin_email', ign_secret('notify_email', CONTACT_EMAIL)));
// If SMTP credentials are present, mail is enabled. No separate switch to forget.
define('MAIL_ENABLED', filter_var(ign_secret('mail_enabled', (ign_secret('smtp_host') && ign_secret('smtp_pass')) ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN));
define('MAIL_FROM', ign_secret('mail_from', ign_secret('from_email', CONTACT_EMAIL)));
define('SMTP_HOST', ign_secret('smtp_host',''));
define('SMTP_PORT', (int)ign_secret('smtp_port',465));
define('SMTP_USER', ign_secret('smtp_user',''));
define('SMTP_PASS', ign_secret('smtp_pass',''));
define('SMTP_SECURE', ign_secret('smtp_secure', ((int)ign_secret('smtp_port',465)===465 ? 'ssl' : 'tls')));
define('SESSION_NAME', 'iss_session');
