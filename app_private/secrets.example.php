<?php
/*
  COPY THIS FILE BY HAND TO:
      domains/invoicegeneratornow.com/ign_private/secrets.php

  This is the SAME layout as the rest of your network (mng_private, cmm_private).
  It sits beside public_html, NOT inside it, so a Git deployment can never delete it.
  The database is created in this same folder for the same reason.

  Never place the completed secrets.php inside public_html or GitHub.
*/
return [
    'admin_password' => 'REPLACE-WITH-A-STRONG-ADMIN-PASSWORD',
    'SITE_SALT'      => 'REPLACE-WITH-A-RANDOM-32-PLUS-CHARACTER-STRING',
    'notify_email'   => 'info@invoicegeneratornow.com',
    'smtp_host'      => 'smtp.hostinger.com',
    'smtp_port'      => 465,
    'smtp_secure'    => 'ssl',
    'smtp_user'      => 'info@invoicegeneratornow.com',
    'smtp_pass'      => 'REPLACE-WITH-THE-MAILBOX-PASSWORD',
    'from_email'     => 'info@invoicegeneratornow.com',
    'from_name'      => 'InvoiceGeneratorNow',
];
