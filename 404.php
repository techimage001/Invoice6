<?php http_response_code(404);require_once __DIR__.'/app_private/bootstrap.php';page_header('Page not found',false,'The requested InvoiceGeneratorNow page could not be found.');?><h1>Page not found</h1><p class="answer-block">This page could not be found, but the document you were looking for is probably one click away. Use the links below to reach the invoice generator, the full template list, or the guide pages.</p>
<h2>Where do you want to go instead?</h2>
<ol class="card gen-steps">
  <li><a href="/">Invoice generator</a> - build any document and download a PDF.</li>
  <li><a href="/templates.php">All 26 templates</a> - invoices, quotes, receipts, purchase orders and more.</li>
  <li><a href="/features.php">Features</a> - everything the tool does.</li>
  <li><a href="/contact.php">Contact</a> - tell us if a link is broken.</li>
</ol>
<p>The address may be incorrect or the page may have moved.</p><div class="actions"><a class="btn" href="/">Go to the homepage</a><a class="btn secondary" href="/features.php">View features</a><a class="btn secondary" href="/contact.php">Contact us</a></div><?php page_footer();?>
