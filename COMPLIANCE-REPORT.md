# V12 compliance review

## Applied

- Brand, domain and contact email are consistent across configuration, metadata, footer, legal pages and email setup.
- PHP and SQLite remain the only required application runtime. No paid API or payment-processing integration is included.
- SQLite, credentials and secrets remain outside `public_html`; a fail-safe `secrets.example.php` is supplied.
- Email verification, expiring password resets, CSRF, password hashing, session security, login throttling, form throttling, honeypots, time traps, JavaScript tokens and a disposable-domain blocklist are present.
- The shared shell adds canonical URLs, robots directives, Open Graph, Twitter cards, JSON-LD, breadcrumbs, full favicon declarations, skip links, mobile navigation, theme choice and cookie controls.
- A real 404, security headers, database-file deny rules and cache policy are configured.
- Search and AI answer bots are allowed. Sitemap lastmod values, IndexNow key/submission, `llms.txt` and a methodology page are included.
- Public pages are linked through categorised footer sections. Private account routes are excluded from the sitemap and blocked in `robots.txt`.
- The service states that it carries no adverts and performs no payment processing.
- Verified and pending accounts are labelled in admin; verified-only account export is included.
- Data export and account deletion are available to signed-in users.
- Copyright and email-deliverability instructions are included.
- Currency handling is country-neutral: 250 country and territory choices automatically select the usual currency, users may override that result, and any other currency can be entered manually with symbol placement, local separators and 0–4 decimal places.

## Not applicable to this product

- The guide's client-side `/app.html` generator, creative preview, image uploads, staged graphic export and three-use lead gate describe content-generator products. InvoiceGeneratorNow is an authenticated record-keeping SaaS, so those mechanics would contradict its data model and workflow.
- Programmatic per-entity page generation and six topical hubs are not justified by the current launch brief. Three distinct commercial-intent SEO guides are included without fabricating search data.
- Unsubscribe is not attached to verification or password-reset messages because those are requested transactional security emails, not marketing. No marketing email feature is included.

## Owner actions still required

- Confirm the full legal operator name, legal status, postal or trading address and company number if applicable. Add these to Terms and Privacy before public launch.
- Name the actual hosting and email processors and confirm their processing locations.
- Confirm final plan prices and the separate, lawful method used to collect subscriptions.
- Create the mailbox, enter SMTP secrets and publish SPF, DKIM and DMARC records.
- Complete live mobile, keyboard, email, cron, backup/restore, security-header and Lighthouse tests after deployment.
- Add real Google and Bing verification codes only after the properties are created.

The code package can implement technical controls, but it cannot truthfully invent the owner's legal identity, configure DNS, create mailboxes or verify live hosting behaviour.
