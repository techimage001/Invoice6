<?php require_once __DIR__.'/app_private/bootstrap.php';page_header('Invoice software pricing',false,'InvoiceGeneratorNow costs nothing in the current release, with every feature included and no card details or payment processing inside the application.');?>
<h1>Invoice software pricing</h1>
<p class="answer-block">InvoiceGeneratorNow costs nothing to use in the current release. Every document type is included at no cost, with no card details requested and no payment processing inside the application.</p>
<div class="pricing section" style="grid-template-columns:repeat(2,1fr);max-width:760px">
<div class="card plan-free"><span class="plan-badge">Current release</span><h2>Everything?</h2><div class="price">£0</div><p class="muted">All 26 document types, no limits on the core workflow.</p><ul><li>Unlimited invoices, quotes and estimates</li><li>Every document type built and stored in your browser</li><li>Your logo on every document</li><li>Download as PDF, PNG or JPG</li></ul><div class="actions"><a class="btn" href="/#invgen">Start building</a></div></div>
<div class="card"><h2>What you keep</h2><p>Your records are yours. Export customers, quotes and invoices to CSV at any time, or download a complete copy of your account.</p><p class="muted">No lock-in. Documents print or save as PDF straight from your browser.</p></div>

</div>
<h2 class="section">Do you take payments or card details?</h2>
<p class="note">No. InvoiceGeneratorNow does not initiate, receive, hold, route, process or verify payments, and never asks for card details. Invoices can display your own static bank-transfer instructions, and you record when a payment has been reported to you.</p>
<?php
faq_block([
 ['How much does the invoice generator cost?','Building and downloading documents costs nothing in the current release, with no watermark and no card details requested. Verifying your email unlocks unlimited use on that browser.'],
 ['Is there a catch?','No advertising, no watermark on your documents and no card details. We ask for an email so you can reach your saved work from another device, and that is the whole exchange. There are no payment links in the product, because we do not process payments at all.'],
 ['Do you take a percentage of my invoices?','No. We never touch your money. Bank details appear on the document as plain text and your customer pays you directly through whatever channel you already use, so nothing is deducted and there is nothing to reconcile with us.'],
 ['What happens to my documents if I stop using the service?','They remain yours. You can export your customers, quotes and invoices as CSV whenever you like, and documents built in the browser are already on your own device. We do not hold your work hostage behind a plan.'],
 ['Do I need a card to create an account?','No. Signing up takes an email address and a single click on the link we send. There is no password to invent, no card form, and no trial that quietly converts into a charge.'],
],'Common questions about cost');
page_footer();?>
