# Public launch checklist

## Hosting setup

Configured identity: InvoiceGeneratorNow, https://InvoiceGeneratorNow.com, info@InvoiceGeneratorNow.com.

1. Point the domain to hosting and enable HTTPS.
2. Confirm PHP 8.1+ with PDO SQLite, OpenSSL, sessions and working outbound email.
3. Keep `app_private`, `storage` and `cron` outside the public web directory.
4. Make `storage` writable by PHP and configure `APP_URL`, `ADMIN_EMAIL`, `MAIL_ENABLED=true` and `MAIL_FROM`.
5. Run `cron/daily.php` and `cron/backup.php` daily. Copy backups off-host regularly.
6. Submit `sitemap.xml` to Google Search Console and Bing Webmaster Tools.

## Owner decisions

- Confirm the full legal operator name, legal status, address and company number if applicable. Add them to the legal pages.
- Add the actual hosting and email providers and their processing locations to the privacy notice.
- Confirm plan names, prices, trial length and how subscriptions are collected outside this application.
- Test email verification, password reset and contact delivery from the live domain.

## Go-live test

- Register, verify and reset a disposable account.
- Confirm failed logins and forms are throttled.
- Complete enquiry → customer → quote → acceptance → job → invoice → paid.
- Print documents to PDF and verify business/bank details.
- Test aged debt, recurring work, CSV/JSON exports, backup/restore and account deletion.
- Check mobile layout, HTTPS headers, 404 handling and cron execution.
