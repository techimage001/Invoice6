<?php require_once __DIR__.'/app_private/bootstrap.php';
// Redirect the old UK-only URL to the global invoice template page (301) to remove the UK-only footprint without breaking links.
header('Location: /invoice-template.php',true,301);
exit;
