<?php require_once __DIR__.'/app_private/bootstrap.php';
page_header('Privacy Policy',false,'Privacy Policy for InvoiceGeneratorNow: what stays in your browser, how email submissions are handled, and how to request deletion.');
?>
<div class="eyebrow">LEGAL</div>
<h1>Privacy Policy</h1>
<p class="answer-block">The Privacy Policy explains what information InvoiceGeneratorNow processes, what remains in the browser, how email submissions are handled and how users can request deletion. The page also shows what information is required and where each stored field can be checked.</p>

<h2>What information is processed?</h2>
<p>Document details are processed in the browser. Drafts remain in browser storage. Email addresses submitted for verification, contact messages and minimal security logs may be stored on the server.</p>

<h2>Does the website carry advertising?</h2>
<p>No. InvoiceGeneratorNow carries no adverts, ad slots or advertising trackers.</p>

<h2>How long is information retained?</h2>
<p>Unverified subscriber records may be removed after a limited administrative period. Contact messages and security logs should be retained only as long as reasonably needed. To have a record deleted outright, use the unsubscribe link in any email we have sent, or email <a href="mailto:<?=e(CONTACT_EMAIL)?>"><?=e(CONTACT_EMAIL)?></a>.</p>
<?php page_footer();?>
