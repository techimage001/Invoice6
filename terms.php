<?php require_once __DIR__.'/app_private/bootstrap.php';
page_header('Terms of service',false,'Terms for using InvoiceGeneratorNow, a free invoice, quote and business document generator.');
?>
<h1>Terms of service</h1>
<p class="answer-block">InvoiceGeneratorNow provides an invoice, quote and business document generator. Every document is built and stored in your own browser. You remain responsible for the accuracy, legality, tax treatment and retention of any document you create. InvoiceGeneratorNow does not initiate, receive, hold, route, process or verify payments.</p>

<div class="card">
<h2>Who may use the service?</h2>
<p>You must be able to enter a binding agreement. There is no account to create and no password to keep confidential: the tool works directly in your browser, and email verification is only used to unlock unlimited use on that browser.</p>

<h2>What is acceptable use?</h2>
<p>Do not use the service unlawfully, upload malicious material, attempt unauthorised access, interfere with availability, misuse another person's data, or send deceptive documents. You must have a lawful reason to enter any third party's details into a document you create.</p>

<h2>What does the service provide?</h2>
<p>The service helps you create and download invoices, quotes and related business documents as a PDF, PNG or JPG. Static bank-transfer instructions may be displayed on a document, but all payment arrangements remain between you and your customer.</p>

<h2>Is my document data stored by InvoiceGeneratorNow?</h2>
<p>No. Everything you enter into the builder, including business details, customer details, prices and any logo, is processed and held in your own browser and is never sent to our servers. If you clear your browser data, that work is gone, so keep your own copy of anything important. The only information we hold on our servers is described in the <a href="/privacy.php">Privacy Policy</a>.</p>

<h2>Is the service always available?</h2>
<p>We aim to provide a reliable service but do not promise uninterrupted or error-free availability. Because documents are held in your own browser, we recommend downloading and keeping your own copy of anything you need to retain. Features may change with reasonable notice.</p>

<h2>Who owns the content?</h2>
<p>You retain full rights to the documents you create. The InvoiceGeneratorNow software, branding and original site copy remain protected by applicable intellectual-property law.</p>

<h2>How is liability limited?</h2>
<p>Nothing excludes liability that cannot legally be excluded. To the fullest extent permitted by law, InvoiceGeneratorNow is not responsible for indirect loss, lost profit, tax errors, customer disputes, or decisions made from inaccurate information entered into a document.</p>

<h2>How can I contact InvoiceGeneratorNow?</h2>
<p>Questions may be sent to <a href="mailto:<?=e(CONTACT_EMAIL)?>"><?=e(CONTACT_EMAIL)?></a>. The site owner must add its full legal identity and trading address before launch if InvoiceGeneratorNow is not itself the registered legal entity.</p>
</div>
<?php page_footer();?>
