# InvoiceGeneratorNow - setup on a fresh repository

## 1. One file to create on the server, by hand
Create this file. It is NEVER in the repository, so deployments cannot delete it.

    domains/invoicegeneratornow.com/ign_private/secrets.php

<?php
return [
    'admin_password' => 'ign2026admin',
    'SITE_SALT'      => 'm69i2a0YCuZcKETmUVUz1Ck2CRa6H17NDnxtPIqb4h3c25RB',
    'NOTIFY_EMAIL'   => 'info@invoicegeneratornow.com',
    'smtp_host'      => 'smtp.hostinger.com',
    'smtp_port'      => 465,
    'smtp_user'      => 'info@invoicegeneratornow.com',
    'smtp_pass'      => 'YOUR-MAILBOX-PASSWORD',
    'from_email'     => 'info@invoicegeneratornow.com',
    'from_name'      => 'InvoiceGeneratorNow',
];

BOTH SITE_SALT and smtp_pass must be non-empty. If either is blank the capture API
reports "Email verification is not configured yet" and refuses to send. That is
deliberate: it fails loudly rather than half-working.

SITE_SALT must never change once live. It signs the unsubscribe tokens, so changing it
invalidates every link already sent.

## 2. Deploy the repository
Files sit at the repository root. package.json is included so Hostinger imports it as a
full PHP site rather than forcing a static deployment.

Root directory: public_html

## 3. Nothing else to configure
The lead database is created automatically at ign_private/leads.sqlite, beside the
secrets file, outside public_html. Deployments cannot touch it.

## Email capture (this is the part that matters)
    api/subscribe.php    capture endpoint, returns JSON with a plain message on any failure
    api/verify.php       link click, marks verified, sets the unlock cookie, notifies admin
    api/status.php       is this browser verified
    api/leads.php        admin dashboard, password login, verified CSV export
    api/unsubscribe.php  deletes the record outright
    api/logout.php       clears the unlock cookie

This is a direct port of the Card Maker Messages system, which is proven in production.
One INSERT then one send. Every failure returns a status code and a message that appears
on screen, so nothing can fail silently.

## Your subscriber list
    https://invoicegeneratornow.com/api/leads.php
Sign in with admin_password from secrets.php.

## First test after deploying
1. Open the site, download three documents. The gate appears on the fourth.
2. Enter a real email. The screen shows the server's own message either way.
3. Open the link in your inbox. You return verified and unlocked on that browser.
4. Open /api/leads.php and the address is listed as verified.

If step 2 shows an error, that text is the real cause. If you want the full picture,
check the mailbox exists in hPanel and that smtp_user is that exact mailbox
(an alias or forwarder cannot authenticate). Hostinger SMTP is smtp.hostinger.com,
port 465, ssl. If mail sends but lands in spam, add SPF and DKIM records under
hPanel > Emails > DNS.
with the exact SMTP error.

## Accounts
register.php, login.php and the dashboard remain for people who save invoices, customers
and quotes. They are separate from email capture and cannot block it.
