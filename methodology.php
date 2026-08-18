<?php require_once __DIR__.'/app_private/bootstrap.php';
page_header('How our documents are built and checked',false,'How our invoice templates are written, checked and kept accurate, and why we publish as a brand rather than under an invented author name.');
article_schema('How our documents are built and checked','How our invoice and business document templates are written, checked and kept accurate.');
?>
<h1>How our documents are built and checked</h1>
<p class="answer-block">Our documents are built from the fields each type genuinely requires, then checked against how the document is actually used in business. We publish as a brand rather than under an invented author name, we do not put dates on guidance that stays true, and we never invent statistics, reviews or quotes.</p>

<h2>Who writes this and who is responsible for it?</h2>
<p>Everything here is published by <?=e(APP_NAME)?> as an organisation. We do not attach a made-up human byline to our guidance, because a fictional author is a false trust signal. The organisation is the author and the publisher, and the contact route is the same for a correction as for anything else.</p>

<h2>How is each document template put together?</h2>
<ol class="card gen-steps">
  <li>Establish what the document is for and at what point in a job it is sent.</li>
  <li>List the fields it genuinely needs, then make the builder enforce the ones that matter.</li>
  <li>Write the guidance in plain language, describing what to include and why.</li>
  <li>Check the wording against how the document behaves in the builder, so the two agree.</li>
  <li>Review it when the tool changes, rather than on a fixed schedule.</li>
</ol>

<h2>Why are there no dates on these pages?</h2>
<p>What belongs on an invoice, and the difference between a credit note and a debit note, does not change from year to year. Stamping a date on guidance that stays true only makes it look stale. We keep dates where they carry meaning, on our legal pages, and leave them off guidance that does not expire.</p>

<h2>What do we refuse to publish?</h2>
<p>We do not publish invented statistics, made-up customer reviews, fabricated quotes or claims we cannot support. Where we describe what the tool does, it is because the tool does it. If a figure is not something we can stand behind, we leave it out rather than fill the space.</p>

<h2>Is this legal or accounting advice?</h2>
<p>No. Our guidance explains what these documents usually contain and how they are normally used. Tax rules, invoice requirements and record-keeping obligations differ by country and by circumstance, so check with a qualified accountant or your tax authority for your own situation.</p>

<?php
faq_block([
 ['Who writes the content on this site?','Everything is written and published by InvoiceGeneratorNow as an organisation rather than under an individual name. We take the view that inventing a human author to look more credible is dishonest, so the brand is named as the author in our page markup and there are no fictional bylines anywhere on the site.'],
 ['Why is there no author photo or byline?','Because there is no single person behind each page and pretending otherwise would be misleading. Guidance about what belongs on a purchase order does not become more accurate by attaching a stock photo and a name to it. We would rather be plainly accountable as a business than manufacture a personality.'],
 ['How do you keep the guidance accurate?','Each page is written alongside the builder it describes, so the fields we say a document needs are the fields the tool actually produces. When we change how a document works, the page describing it is updated in the same release rather than drifting out of step.'],
 ['Do you use any invented reviews or statistics?','No. There are no testimonials, star ratings or usage statistics anywhere on this site, because we will not publish figures we cannot support. Where we state something factual about the tool, such as the number of currencies, it is a number you can check by using it.'],
 ['Can I suggest a correction?','Yes, and we would rather hear it than not. Use the contact form to tell us what is wrong and where you saw it. If guidance turns out to be unclear or incorrect for a particular country, that is worth knowing and worth fixing.'],
],'Questions about how we publish');
page_footer();
