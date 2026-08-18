# Deploying InvoiceGeneratorNow to Hostinger via GitHub

## The one thing that matters

This is a **PHP + SQLite application served by Apache**. It deploys exactly the
same way Card Maker Messages does: through **classic PHP hosting Git
deployment**, NOT through the Node.js / Web Apps importer.

The screen titled "Select Git repository to import" that lives under
`quick-install-node-addon` is the Node.js flow. It cannot host PHP. It will
either reject the repo outright, or offer to "continue as a static website" —
which is worse, because static hosting has no PHP interpreter and would serve
every `.php` file as readable plain text, exposing your source.

Neither adding nor removing `package.json` changes this. Card Maker Messages
has a `package.json` and still does not deploy through that screen; the file is
local tooling metadata that Hostinger never executes.

## Correct route

1. hPanel > **Websites** > **Add Website**
2. Choose **Empty PHP/HTML website** (not Node.js, not Web App)
3. Assign the domain `invoicegeneratornow.com`
4. Open that site's dashboard > **Advanced** > **GIT**
5. Connect the repository and set the branch, then Deploy

Hostinger copies the **repository root** into the document root, so `index.php`
must sit at the top level of the repo. This archive is already flat — unzip it
and the contents go straight to the repo root, with no wrapper folder.

## Pushing to GitHub

Use **GitHub Desktop**, or from a terminal:

    git add .
    git commit -m "V58"
    git push

Do **not** use GitHub's drag-and-drop "Upload files" page. It silently skips
every file whose name begins with a dot, and it caps at 100 files per upload.
Dropping `.htaccess` would leave `app_private/`, `storage/`, `tests/`, `tools/`
and `cron/` publicly readable, and would remove the block on executing PHP
inside `uploads/`.

After pushing, open the repo on GitHub and confirm `.htaccess` is listed at the
root. If it is missing, the upload method dropped it.

## Alternative: File Manager

If Git is being awkward, hPanel > **File Manager** > `public_html`, upload this
zip, extract it there, then delete the zip. No file-count limit and dotfiles
survive. This is the fastest way to get the site live.

## After deploying

- Make `storage/` and `uploads/logos/` writable (0775).
- Copy `app_private/secrets.example.php` to `ign_private/secrets.php` in the
  folder **beside** `public_html`, not inside it, and fill in SMTP and APP_URL.
  `config.php` looks there first. Keeping it above the web root means a deploy
  can never delete it and the web server can never serve it.
- You can also set `DB_PATH` there so the SQLite database lives above the web
  root and survives every deploy.
- Enable **Force HTTPS** in hPanel once the certificate has issued.

## Verify it worked

- `https://invoicegeneratornow.com/` loads the generator.
- `https://invoicegeneratornow.com/app_private/bootstrap.php` returns **404**.
  If it shows code, `.htaccess` did not make it into the repo — fix that before
  doing anything else.
