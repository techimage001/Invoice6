<?php require_once __DIR__.'/app_private/bootstrap.php';
$cats=currency_catalog();$map=country_currency_map();
$curJson=[];foreach($cats as $code=>$c){$curJson[$code]=['n'=>$c[0],'s'=>$c[1],'p'=>$c[2],'d'=>$c[3]];}
$default='USD';
page_header('Invoice generator',false,'Invoice generator: create a professional invoice in any currency and download a clean PDF. No sign-up to start, no watermark, nothing sent to us.');
?>
<section class="gen-hero">
  <div class="eyebrow">INVOICE GENERATOR</div>
  <h1>Make a professional invoice in under a minute</h1>
  <p class="answer-block">Fill in the builder, watch the total update as you type, and download a clean PDF. No sign-up to start, works in any country and currency, and your details never leave your device unless you choose to save them.</p>
</section>

<div id="invgen" class="gen-grid" data-currencies='<?=e(json_encode($curJson,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?>' data-countries='<?=e(json_encode($map,JSON_UNESCAPED_SLASHES))?>' data-default="<?=e($default)?>" data-free="3" data-template="modern">
  <details id="historyPanel" class="history-panel" hidden>
    <summary>Recently used <span class="muted">(saved on this device only)</span></summary>
    <div id="historyList" class="history-list"></div>
  </details>
  <h2 class="build-heading">Build Document</h2>
  <form class="gen-form" onsubmit="return false" novalidate>
    <div class="field-card doc-controls" id="docControls">
      <div class="form-grid">
        <div class="full"><label for="g_doctype">Document</label>
          <select id="g_doctype">
            <optgroup label="Sales">
              <option value="invoice">Invoice</option>
              <option value="tax-invoice">Tax invoice</option>
              <option value="commercial-invoice">Commercial invoice</option>
              <option value="proforma-invoice">Proforma invoice</option>
              <option value="quote">Quote</option>
              <option value="estimate">Estimate</option>
              <option value="sales-order">Sales order</option>
            </optgroup>
            <optgroup label="Stage billing">
              <option value="deposit-invoice">Deposit invoice</option>
              <option value="progress-invoice">Progress invoice</option>
              <option value="final-invoice">Final invoice</option>
            </optgroup>
            <optgroup label="Adjustment">
              <option value="credit-note">Credit note</option>
              <option value="debit-note">Debit note</option>
              <option value="return-note">Return note</option>
            </optgroup>
            <optgroup label="Payment">
              <option value="receipt">Receipt</option>
              <option value="payment-reminder">Payment reminder</option>
              <option value="account-balance-letter">Account balance letter</option>
            </optgroup>
            <optgroup label="Operational">
              <option value="purchase-order">Purchase order</option>
              <option value="work-order">Work order / job sheet</option>
              <option value="delivery-note">Delivery note</option>
              <option value="packing-slip">Packing slip</option>
              <option value="waybill">Waybill</option>
              <option value="goods-received-note">Goods received note</option>
              <option value="completion-certificate">Completion certificate</option>
              <option value="statement">Client statement</option>
            </optgroup>
            <optgroup label="Time &amp; expenses">
              <option value="timesheet">Timesheet</option>
              <option value="expense-report">Expense report</option>
            </optgroup>
          </select>
        </div>
        <div class="full"><label for="g_doclang">Document language</label>
          <select id="g_doclang">
            <option value="en">English</option><option value="fr">Français</option><option value="es">Español</option>
            <option value="de">Deutsch</option><option value="it">Italiano</option><option value="pt">Português</option>
          </select>
          <small class="muted">The document labels appear in this language. The app stays in English.</small>
        </div>
      </div>
    </div>

    <details class="doc-options">
      <summary>Currency, logo &amp; options</summary>
      <div class="doc-options-grid">
        <div><label for="o_currency">Currency</label><select id="o_currency"></select></div>
        <div><label for="o_country">Country</label><select id="o_country"><option value="">Select country</option></select></div>
        <div><label for="o_terms">Payment terms</label><select id="o_terms"><option value="">None</option><option>Due on receipt</option><option>Net 7</option><option>Net 14</option><option>Net 30</option><option>Net 60</option></select></div>
        <div><label for="o_status">Status</label><select id="o_status"><option value="">No status</option><option>Unpaid</option><option>Part-paid</option><option>Paid</option></select></div>
        <div><label for="o_disctype">Discount type</label><select id="o_disctype"><option value="pct">Percent %</option><option value="fixed">Fixed amount</option></select></div>
        <div><label for="o_doclang">Document language</label><select id="o_doclang"></select></div>
        <div class="full logo-field"><label for="o_logo"><strong>Company logo</strong> (optional)</label>
          <input id="o_logo" type="file" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
          <div class="logo-row"><img id="o_logoThumb" alt="Your uploaded logo" hidden><button type="button" id="o_logoRemove" class="btn secondary small" hidden>Remove logo</button></div>
          <small class="muted">PNG or SVG, around 400 x 200 px, under 1 MB. Stays on your device.</small></div>
        <div class="full"><label class="check"><input type="checkbox" id="o_taxincl"> Prices include tax</label></div>
        <div class="full"><label class="check"><input type="checkbox" id="o_sameaddr" checked> Delivery address same as billing</label></div>
        <div class="full"><label class="check"><input type="checkbox" id="o_showsig"> Add a signature line</label></div>
      </div>
    </details>
    <!-- These panels are the state store. The document itself is now the editing
         surface, so they are visually hidden but must remain in the DOM. Advanced
         controls that have no place on the document stay visible below. -->
    <div class="legacy-fields" aria-hidden="true">
    <div class="field-card"><h2 data-role="from-heading">Your business</h2>
      <div class="form-grid">
        <div class="full"><label>Business name</label><input id="g_bizname" placeholder="Your business name"></div>
        <div><label>Email</label><input id="g_bizemail" type="email" placeholder="you@business.com"></div>
        <div><label>Phone (optional)</label><input id="g_bizphone" placeholder="Phone"></div>
        <div class="full"><label>Address</label><textarea id="g_bizaddr" rows="2" placeholder="Street, city, postcode, country"></textarea></div>
        <div class="adv-only"><label>Tax registration number</label><input id="g_biztax" placeholder="VAT / GST number"></div>
        <div><label for="g_country">Country</label><select id="g_country"><option value="">Select country</option></select></div>
        <div><label for="g_currency">Currency</label><select id="g_currency"></select></div>
        <div class="full logo-field"><label for="g_logo"><strong>Upload your company logo</strong> (optional)</label><input id="g_logo" type="file" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"><div class="logo-row"><img id="logoThumb" alt="Your uploaded logo" hidden><button type="button" id="logoRemove" class="btn secondary small" hidden>Remove logo</button></div><small class="muted">Best results: PNG or SVG, around 400 x 200 px (landscape), under 1 MB. Appears at the top of your document. Stays on your device and is never uploaded.</small></div>
      </div>
    </div>
    <div class="field-card"><h2 data-role="to-heading">Bill to</h2>
      <div class="form-grid">
        <div class="full"><label>Customer name</label><input id="g_clientname" placeholder="Customer or company"></div>
        <div><label>Email (optional)</label><input id="g_clientemail" type="email" placeholder="name@company.com"></div>
        <div class="full"><label>Billing address (optional)</label><textarea id="g_clientaddr" rows="2" placeholder="Customer address"></textarea></div>
        <div class="full adv-only"><label class="check"><input type="checkbox" id="g_sameaddr" checked> Delivery address same as billing</label></div>
        <div class="full adv-only" id="deliverWrap" hidden><label>Delivery address</label><textarea id="g_deliveraddr" rows="2" placeholder="Where the goods are delivered"></textarea></div>
      </div>
    </div>
    <div class="field-card"><h2 data-role="meta-heading">Details</h2>
      <div class="form-grid">
        <div><label data-role="number-label">Invoice number</label><input id="g_number" value="INV-0001"></div>
        <div><label for="g_issue">Issue date</label><input id="g_issue" type="date" aria-describedby="dateFmtHint"><small class="muted date-hint" id="dateFmtHint">Prints on the document as YYYY-MM-DD</small></div>
        <div data-role="due-field"><label for="g_due" data-role="due-label">Due date</label><input id="g_due" type="date">
          <div class="due-presets" role="group" aria-label="Set the due date relative to the issue date">
            <button type="button" class="due-chip" data-days="0">On receipt</button>
            <button type="button" class="due-chip" data-days="7">7 days</button>
            <button type="button" class="due-chip" data-days="14">14 days</button>
            <button type="button" class="due-chip" data-days="30">30 days</button>
          </div>
        </div>
        <div data-role="figure-amount" hidden><label for="g_balamt">Balance owed</label><input id="g_balamt" type="number" step="0.01" min="0" placeholder="0.00"></div>
        <div data-role="figure-amtdue" hidden><label for="g_amtdue">Amount outstanding</label><input id="g_amtdue" type="number" step="0.01" min="0" placeholder="0.00"></div>
        <div data-role="figure-asat" hidden><label for="g_asat">As at date</label><input id="g_asat" type="date"></div>
        <div data-role="figure-origdue" hidden><label for="g_origdue">Originally due</label><input id="g_origdue" type="date"></div>
        <div data-role="opening-field" hidden><label for="g_openbal">Opening balance</label><input id="g_openbal" type="number" step="0.01" placeholder="0.00"></div>
        <div data-role="period-field" hidden><label for="g_period">Period covered</label><input id="g_period" placeholder="1 - 31 August 2026"></div>
        <div data-role="route-origin" hidden><label for="g_origin">Origin</label><input id="g_origin" placeholder="Country or city of origin"></div>
        <div data-role="route-dest" hidden><label for="g_destination">Destination</label><input id="g_destination" placeholder="Country or city of destination"></div>
        <div data-role="route-carrier" hidden><label for="g_carrier">Carrier</label><input id="g_carrier" placeholder="Carrier name"></div>
        <div data-role="incoterms-field" hidden><label for="g_incoterms">Incoterms</label><input id="g_incoterms" placeholder="e.g. DAP, FOB, CIF"></div>
        <div class="adv-only" data-role="po-field"><label>Purchase order / reference</label><input id="g_po" placeholder="PO or reference"></div>
        <div class="adv-only" data-role="terms-field"><label>Payment terms</label>
          <select id="g_terms"><option value="">None</option><option>Due on receipt</option><option>Net 7</option><option>Net 14</option><option>Net 30</option><option>Net 60</option></select>
        </div>
        <div class="adv-only" data-role="original-field" hidden><label data-role="original-label">Original invoice number</label><input id="g_original" placeholder="Invoice this relates to"></div>
        <div class="adv-only" data-role="reason-field" hidden><label>Reason</label><input id="g_reason" placeholder="Reason for credit"></div>
        <div><label>Tax label</label><input id="g_taxname" value="Tax" placeholder="VAT, GST, Sales tax"></div>
        <div><label>Tax rate %</label><input id="g_taxrate" type="number" step="0.01" min="0" value="0"></div>
        <div class="adv-only"><label class="check"><input type="checkbox" id="g_taxincl"> Prices include tax</label></div>
      </div>
    </div>
    <div class="field-card"><h2 data-role="items-heading">Line items</h2>
      <div class="line-head adv-head"><span>Description</span><span>Qty</span><span>Unit price</span><span class="adv-only">Tax %</span><span>Amount</span><span></span></div>
      <div id="genItems" role="group" aria-label="Line items">
        <?php for($i=0;$i<3;$i++):?><div class="line-item"><label class="li-f li-f-desc"><span>Description</span><input class="li-desc" placeholder="Description of work or item"></label><label class="li-f li-f-qty"><span>Qty</span><input class="li-qty" type="number" step="0.01" min="0" value="1" aria-label="Quantity"></label><label class="li-f li-f-price"><span>Unit price</span><input class="li-price" type="number" step="0.01" min="0" placeholder="0.00" aria-label="Unit price"></label><label class="li-f li-f-tax"><span>Tax %</span><input class="li-tax" type="number" step="0.01" min="0" placeholder="" aria-label="Line tax percent"></label><span class="li-total" aria-hidden="true">&#8212;</span><button type="button" class="li-remove" aria-label="Remove line">&#10005;</button></div><?php endfor;?>
      </div>
      <button type="button" class="btn secondary small" id="genAddLine">&#43; Add line</button>
    </div>
    <div class="field-card adv-only"><h2>Discount, shipping &amp; deposit</h2>
      <div class="form-grid">
        <div><label>Discount</label><input id="g_discval" type="number" step="0.01" min="0" value="0"></div>
        <div><label>Discount type</label><select id="g_disctype"><option value="pct">Percent %</option><option value="fixed">Fixed amount</option></select></div>
        <div><label>Shipping</label><input id="g_shipping" type="number" step="0.01" min="0" value="0"></div>
        <div><label>Deposit / amount paid</label><input id="g_deposit" type="number" step="0.01" min="0" value="0"></div>
      </div>
    </div>
    <div class="field-card"><h2 data-role="notes-heading">Notes &amp; payment</h2>
      <div class="form-grid">
        <div class="full adv-only" data-role="status-field"><label>Status</label><select id="g_status"><option value="">No status</option><option>Unpaid</option><option>Part-paid</option><option>Paid</option></select></div>
        <div class="full"><label>Notes (optional)</label><textarea id="g_notes" rows="2" placeholder="Payment terms, reference or a thank-you"></textarea></div>
        <div class="full"><label>Bank / payment details (optional)</label><textarea id="g_bank" rows="2" placeholder="Account name and transfer instructions"></textarea></div>
        <div class="full adv-only"><label>Pay via (optional)</label><input id="g_payvia" placeholder="Your PayPal.me link or bank reference"><small class="muted">Plain text on the document. We never process payments.</small></div>
        <div class="full adv-only"><label class="check"><input type="checkbox" id="g_showsig"> Add a signature line</label></div>
      </div>
    </div>
    </div>
  </form>

  <aside class="gen-preview-wrap">
    <div class="template-bar" role="group" aria-label="Choose a template">
      <button type="button" data-template="modern" class="tmpl-chip is-active">Modern</button>
      <button type="button" data-template="clean" class="tmpl-chip">Clean</button>
      <button type="button" data-template="minimal" class="tmpl-chip">Minimal</button>
      <button type="button" data-template="corporate" class="tmpl-chip">Corporate</button>
      <button type="button" data-template="blue" class="tmpl-chip">Professional Blue</button>
      <button type="button" data-template="elegant" class="tmpl-chip">Elegant</button>
      <button type="button" data-template="creative" class="tmpl-chip">Creative</button>
      <button type="button" data-template="construction" class="tmpl-chip">Construction</button>
      <button type="button" data-template="cleaning" class="tmpl-chip">Cleaning</button>
      <button type="button" data-template="consulting" class="tmpl-chip">Consulting</button>
      <button type="button" data-template="healthcare" class="tmpl-chip">Healthcare</button>
      <button type="button" data-template="mono" class="tmpl-chip">Black &amp; White</button>
    </div>
    <div class="preview-head"><div class="live-badge">Live preview &#183; updates as you type</div><button type="button" id="previewExpand" class="btn secondary small" aria-expanded="false">Full screen</button></div>
    <button type="button" id="previewClose" class="preview-close" aria-label="Close full screen preview">&#10005; Close</button><article class="doc" id="invPreview" aria-live="polite" role="region" aria-label="Live document preview"></article>
    <p class="muted gen-privacy">Your invoice is built in your browser. Nothing is sent to us.</p>
  </aside>
  <div class="gen-actions-bar">
    <div class="gen-actions">
      <button id="genDownload" class="btn">Download PDF</button>
      <button id="genDownloadPng" class="btn secondary small" type="button">PNG</button>
      <button id="genDownloadJpg" class="btn secondary small" type="button">JPG</button>
      <button id="genShare" class="btn secondary small" type="button">Share</button>
      <button id="genDuplicate" class="btn secondary small" type="button" title="Start a new document from this one">Duplicate</button>
      <button id="genReset" class="btn secondary small" type="button">Reset</button>
      <span class="muted" id="genUses"></span>
    </div>
    <div id="shareMenu" class="share-menu" hidden>
      <div class="share-group">
        <span class="share-label">Send the document</span>
        <button type="button" class="share-opt primary-opt" id="shareDocPdf">Share as PDF</button>
        <button type="button" class="share-opt" id="shareDocPng">Share as PNG</button>
        <button type="button" class="share-opt" id="shareDocJpg">Share as JPG</button>
        <p class="muted share-note" id="shareNote">Opens your phone&#8217;s share sheet, where WhatsApp, Mail and Messages receive the actual file.</p>
      </div>
      <div class="share-group">
        <span class="share-label">Send a link instead</span>
        <a id="shareWhatsapp" class="share-opt" target="_blank" rel="noopener">WhatsApp link</a>
        <a id="shareEmail" class="share-opt">Email link</a>
        <button id="shareCopy" class="share-opt" type="button">Copy link</button>
        <p class="muted share-note">A link cannot carry an attachment, so these send the message and a link back to the site only.</p>
      </div>
    </div>
  </div>
</div>

<section class="landing-sections"><h2>What do you get with this invoice generator?</h2><div class="trust-band"><div class="card"><strong>No cost today</strong><span>No sign-up to start, no card details requested</span></div><div class="card"><strong>150+</strong><span>Currencies, formatted correctly</span></div><div class="card"><strong>No watermark</strong><span>Clean PDF every time</span></div><div class="card"><strong>Private</strong><span>Your details stay in your browser</span></div></div></section>

<section class="landing-sections"><h2>How do you make an invoice?</h2><ol class="card gen-steps"><li>Add your business, your customer and your line items.</li><li>Pick your country so the currency and totals are right.</li><li>Download a clean PDF, or create an account to save, track and chase it.</li></ol></section>

<section class="landing-sections"><h2>How does it compare with other generators?</h2><div class="mobile-table"><table><thead><tr><th>What matters</th><th>InvoiceGeneratorNow</th><th>Typical online generators</th></tr></thead><tbody><tr><td>Create without an account</td><td>Yes, no account needed to start</td><td>Often blocked at download</td></tr><tr><td>Watermark on the PDF</td><td>Never</td><td>Common</td></tr><tr><td>Where your data goes</td><td>Stays in your browser</td><td>Usually sent to their server</td></tr><tr><td>Any country and currency</td><td>Any country, 153 currencies</td><td>Often US only</td></tr><tr><td>Card details required</td><td>Never</td><td>Pushed hard</td></tr></tbody></table></div></section>


<div id="gateModal" class="gate" hidden role="dialog" aria-modal="true" aria-labelledby="gateTitle">
  <div class="gate-card">
    <button type="button" id="gateClose" class="gate-close" aria-label="Close">&#10005;</button>
    <h2 id="gateTitle">Verify your email to keep going</h2>
    <p class="muted">You have used your free documents on this browser. Enter your email and we send a private verification link. Opening it unlocks unlimited use here. Nothing is charged and no card details are requested.</p>
    <form id="signupForm" class="form">
      <input type="text" name="company" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
      <input type="hidden" id="signupStarted" value="<?=(int)round(microtime(true)*1000)?>">
      <div class="form-grid">
        <div class="full"><label for="signupEmail">Email</label><input type="email" id="signupEmail" name="email" required autocomplete="email"></div>
        <div class="full"><button class="btn" type="submit">Email my verification link</button></div>
      </div>
    </form>
    <p class="muted" id="signupStatus" aria-live="polite"></p>
  </div>
</div>
<section class="landing-sections">
<?php
related_strip([
  ['/invoice-template.php','Invoice template','Bill a customer with itemised lines, tax and a due date.'],
  ['/quote-template.php','Quote template','Give a fixed price with a valid-until date, then convert it.'],
  ['/receipt-template.php','Receipt template','Confirm a payment against the invoice it settles.'],
  ['/purchase-order-template.php','Purchase order','Order from a supplier with a PO number and delivery date.'],
  ['/credit-note-template.php','Credit note','Cancel or reduce an amount you have already invoiced.'],
  ['/templates.php','All 26 templates','Every document generator, grouped by what it is for.'],
],'Which document do you need?');

faq_block([
 ['How do I make an invoice online?','Fill in your business name, your customer, and the work with quantities and prices. Totals update as you type. Choose your country so the currency, symbol and decimals are correct, then download a clean PDF. The first invoices need no account at all, there is no watermark on the file, and no card details are requested at any point.'],
 ['What should be on an invoice?','An invoice should carry your business name and contact details, the customer name and address, a unique invoice number, the issue date and the due date, an itemised list of the goods or services with quantities and prices, any tax and discount, the total amount due, and clear instructions on how to pay. Adding payment terms such as Net 14 makes the due date unambiguous.'],
 ['Do I need an account to use the invoice generator?','No. You can build and download documents without signing up. Verifying your email unlocks unlimited use on that browser, at no cost in the current release. There is no password, no account and no card details are ever requested.'],
 ['Can I use this invoice generator outside the US or UK?','Yes. Choose any country and the currency, symbol position and decimal places are set for you, covering 153 currencies. You can also set a different currency on an individual document for an overseas customer, and produce the document labels in English, French, Spanish, German, Italian or Portuguese.'],
 ['Is my invoice data private?','Your details are built into the document in your own browser and are not sent to us. Your work is kept on your device so a refresh does not lose it. If you create an account, the documents you choose to save are stored so you can reach them from another device, and you can export everything as CSV whenever you want.'],
 ['How do I get paid faster on late invoices?','Send the invoice promptly, set a clear due date, and state exactly how to pay. Late payment is usually forgetfulness rather than refusal, so a polite nudge a few days before the due date and again shortly after recovers most of it. With an account you can schedule those reminders and see every overdue invoice in one list.'],
 ['Can I put my company logo on the invoice?','Yes. Upload a logo and it appears at the top of the document. A landscape PNG or SVG around 400 by 200 pixels and under 1 MB gives the cleanest result. The file is read in your browser and placed on the document directly, so it is never uploaded to us.'],
 ['What is the difference between an invoice and a quote?','A quote sets out a fixed price before work starts, so the customer can accept it. An invoice requests payment once the work is done. The usual sequence is quote, then job, then invoice, and this tool converts an accepted quote into an invoice in one click without retyping the customer or the line items.'],
]);
?>
</section>
<?php page_footer();?>
