<?php require_once __DIR__.'/app_private/bootstrap.php';
/* There is no separate sign-in. Access is unlocked by verifying an email at the generator,
   exactly as on the rest of the network. Anyone landing here is sent to the tool. */
header('Location: /#invgen', true, 302);
exit;
