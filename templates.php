<?php require_once __DIR__.'/app_private/bootstrap.php';
$reg=doc_registry();
page_header('Business document templates',false,'Business templates: invoice, quote, receipt, purchase order and credit note. Fill in the builder and download a clean PDF in any currency.');
$app=['@type'=>'WebApplication','name'=>'Business document templates','applicationCategory'=>'BusinessApplication','operatingSystem'=>'Any','offers'=>['@type'=>'Offer','price'=>'0','priceCurrency'=>'USD']];
echo '<script type="application/ld+json">'.json_encode(['@context'=>'https://schema.org','@graph'=>[$app]],JSON_UNESCAPED_SLASHES).'</script>';
?>
<article class="landing-sections">
  <div class="eyebrow">BUSINESS TEMPLATES</div>
  <h1>Business document templates</h1>
  <p class="answer-block">These templates cover the documents a small business sends every week: invoices, quotes, estimates, receipts, purchase orders, credit notes and more. Pick one, fill in the builder, and download a clean PDF in any currency. There is no watermark, no sign-up to start, and your details stay in your browser.</p>
  <div class="grid three template-grid">
    <?php foreach($reg as $k=>$d): ?>
      <a class="card template-card" href="/<?=e($d['slug'])?>.php">
        <span class="template-thumb" aria-hidden="true"><?=e(strtoupper(substr($d['label'],0,1)))?></span>
        <strong><?=e($d['label'])?></strong>
        <span class="muted"><?=e(substr($d['answer'],0,96))?>&#8230;</span>
      </a>
    <?php endforeach; ?>
  </div>
  <h2>How do these templates work?</h2>
  <ol class="gen-steps card">
    <li>Choose the document you need, then open its builder.</li>
    <li>Add your business, your customer and the line items. Totals update as you type.</li>
    <li>Download a clean PDF, or create an account to save, reuse and track it.</li>
  </ol>
</article>
<?php
faq_block([
 ['Which business document do I actually need?','It depends where you are in the job. Before work, send a quote or an estimate. To order from a supplier, raise a purchase order. After work, send an invoice, then a receipt once paid. To correct an invoice, use a credit note to reduce it or a debit note to increase it. Each template on this page explains when it applies.'],
 ['Are all these document templates free?','Yes. Every template here builds in your browser and downloads as a clean PDF with no watermark and no card details requested. There is no account to create.'],
 ['Can I convert one document into another?','Yes. An accepted quote or estimate becomes an invoice in one click, keeping the customer and the line items, and a paid invoice can become a receipt or a credit note. The original document stays unchanged and the new one gets its own number, so your records stay clean.'],
 ['What is the difference between a delivery note and a packing slip?','Both travel with goods and neither normally shows prices. A delivery note is the record the recipient checks and signs to confirm what arrived. A packing slip is more commonly used inside the parcel to list its contents. Many businesses use one or the other rather than both.'],
 ['Do these templates work outside my country?','Yes. Choose any country and the currency, symbol and decimal format follow, across 153 currencies. You can also produce the document labels in six languages while keeping the app itself in English, which helps when billing a customer abroad.'],
],'Choosing the right document');
page_footer(); ?>
