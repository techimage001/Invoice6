# InvoiceGeneratorNow V19 — changelog

## Deployment fix (the 403)
- Repo is flat at the root (no public_html folder). Includes package.json so Hostinger deploys it as a full PHP site, not static.
- .htaccess has DirectoryIndex index.php.

## Funnel & bug fixes
- Signup modal no longer appears on page load. It shows only after the free downloads are used, on Download. (.gate is display:none by default; revealed via .is-open.)
- Registration is now email-only MAGIC LINK. No password to invent, no "10 characters", no js_token trap. The link both signs in and verifies the email in one click.
- Humane rate limiting (8 sign-in emails per hour per IP, counted only on genuine sends). No more "Too many registrations" lockouts for real users or shared networks.
- Footer relabelled from "UK invoice generator" to a full generator list; the old /invoice_generator_uk.php now 301-redirects to /invoice-template.php.
- login.php also uses magic link; returning users just enter their email.

## Invoice builder (Simple / Advanced)
- Document selector: Invoice, Tax invoice, Proforma, Quote, Estimate, Credit note, Receipt, Purchase order, Delivery note, Client statement — each with correct fields, headings and behaviour.
- Advanced fields: per-line and invoice-level discount, shipping, per-line + named tax, tax-inclusive pricing, deposit/part-payment with balance due, PO/reference, payment terms, billing + delivery address, status, signature line, optional "Pay via" text.
- Tax invoice validates the tax registration number before download.
- Multi-currency: symbol/position/decimals set from country, correct on the document.
- One-click document behaviour by type (numbering prefix, valid-until vs due, original invoice ref, notices).
- In-document label translation: English, French, Spanish, German, Italian, Portuguese.
- 12 original templates (accent + header treatment), chosen from a chip bar.

## SEO / AEO
- /templates.php hub + 10 dedicated pages: invoice, tax-invoice, proforma-invoice, quote, estimate, credit-note, receipt, purchase-order, delivery-note, statement.
- Each page: 40–60 word answer-first block, question-shaped H2s, extractable numbered list, 5 unique FAQs, FAQPage + HowTo + WebApplication JSON-LD parsed from the same arrays that render, breadcrumbs, sibling links, footer listing every generator.
- Sitemap regenerated; robots.txt already allows AI answer bots.
- No reviews (removed per request). Factual trust strip instead. No fabricated content. No ads. No em-dashes. UK English.

## You must do after upload
1. Deploy the repo to Hostinger as a full site (package.json ensures this). Root directory public_html or the domain root.
2. Place secrets.php outside public_html (or in app_private) with SMTP settings. MAIL must be enabled or magic links will not send.
3. Configure SPF, DKIM and DMARC in Hostinger/DNS, then send yourself a real sign-in link to confirm it does not land in spam. This is the one thing I cannot do from here and it is essential for the funnel.

## Verified here (statically) / needs live check
- Verified: 60 structural QA checks pass; app.js parses; homepage and template pages render correctly (screenshots shown).
- Needs your live check: PHP execution on Hostinger, SMTP delivery of magic links, PDF print output on your device, and the builder JS in a real browser session.
