# Deploying InvoiceGeneratorNow to Hostinger (Git auto-deploy)

## Why the 403 happened
Hostinger copies your **repository root** into the site's document root. The
earlier package kept the web files inside a `public_html/` subfolder, so the
document root had no `index.php` at the top and Hostinger returned 403.

## What changed
The site is now **flattened**: `index.php`, `assets/`, `robots.txt`, `sitemap.xml`
and everything else live at the repository root — the same layout as the rest of
your network (e.g. TapPill). No subfolder, no rewrite shim. It works as soon as
the files are at the repo root.

## Deploy steps
1. Put the contents of this zip at the **root** of your `Invoicegeneratornow` repo
   (not inside a subfolder).
2. **Include the dotfiles.** GitHub's drag-and-drop "Upload files" screen silently
   skips files that start with a dot (`.htaccess`, `app_private/.htaccess`, etc.).
   Use one of these so they are not dropped:
   - GitHub Desktop, or `git add . && git commit && git push` from your machine, or
   - upload a zip through a client that keeps dotfiles.
   After pushing, open the repo on GitHub and confirm `.htaccess` is listed at the root.
3. Trigger the Git deployment in hPanel (or push to the deployed branch).
4. Visit the site root — you should see the invoice generator.

## The dotfiles matter for security, not for loading
Even if `.htaccess` is missing, the homepage still loads (index.php is at the root).
But the `.htaccess` files are what block `/app_private/`, `/storage/`, `/tests/`,
`/tools/` and `/cron/` from the web. So make sure they are present.

## Belt and braces: keep secrets ABOVE the web root
Create a folder next to (not inside) the deployed site, named `ign_private`:

    domains/invoicegeneratornow.com/ign_private/secrets.php
    domains/invoicegeneratornow.com/public_html/   <-- the deployed repo (document root)

`config.php` looks there first, then falls back to `app_private/secrets.php`.
Putting secrets above the web root means they can never be served, even if a
dotfile is missed. Copy `app_private/secrets.example.php` into it and fill in the
SMTP and APP_URL values. You can also set `DB_PATH` there to keep the SQLite
database above the web root too.

## Also before go-live
- Make `storage/` and `uploads/logos/` writable by the web user (0775).
- HTTPS: enable "Force HTTPS" in hPanel once the SSL certificate is active (the
  .htaccess does not force it, to avoid a broken redirect before the cert issues).
- Publish the SPF, DKIM and DMARC records from EMAIL-SETUP.md.
