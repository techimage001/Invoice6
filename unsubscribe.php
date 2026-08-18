<?php require_once __DIR__.'/app_private/bootstrap.php';
/* Honours the promise made on the privacy page: every email carries an unsubscribe link,
   and unsubscribing DELETES the record outright rather than flagging it. */
$done=false;$notFound=false;
$token=(string)($_GET['u']??'');
$email=strtolower(trim((string)($_GET['e']??'')));
if($token!=='' && $email!==''){
  if(hash_equals(unsubscribe_token($email),$token)){
    try{
      $st=db()->prepare('SELECT id FROM users WHERE email=?');$st->execute([$email]);$row=$st->fetch();
      if($row){
        db()->prepare('DELETE FROM users WHERE id=?')->execute([(int)$row['id']]);  // cascades to related rows
        $done=true;
      } else { $notFound=true; }
    }catch(Throwable $e){ error_log('[unsubscribe] '.$e->getMessage()); }
  }
}
page_header('Unsubscribe',false,'Unsubscribe from InvoiceGeneratorNow emails. Your address and saved data are deleted straight away, not simply flagged.');
?>
<h1>Unsubscribe</h1>
<?php if($done):?>
  <p class="answer-block">You have been unsubscribed and your record has been deleted. Your email address, account and saved documents have been removed from our database entirely, not merely marked as inactive. Nothing further will be sent to you.</p>
  <div class="actions"><a class="btn" href="/">Back to the invoice generator</a></div>
<?php elseif($notFound):?>
  <p class="answer-block">That address is not on our list, so there is nothing to remove. You will not receive email from us.</p>
  <div class="actions"><a class="btn" href="/">Back to the invoice generator</a></div>
<?php else:?>
  <p class="answer-block">This unsubscribe link is not valid or has already been used. Use the link at the foot of any email we have sent you, or contact us and we will remove your address by hand.</p>
  <div class="actions"><a class="btn" href="/contact.php">Contact us</a></div>
<?php endif;?>
<h2>What happens to your data when you unsubscribe?</h2>
<ol class="gen-steps">
  <li>Your email address is deleted from our database, not marked inactive.</li>
  <li>Your account and any documents saved to it are removed with it.</li>
  <li>No further email is sent to that address.</li>
  <li>Documents you built in your own browser stay on your device and are unaffected.</li>
</ol>
<?php page_footer();?>
