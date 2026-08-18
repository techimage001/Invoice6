# V58 — fatal 500 fix + mobile/cross-device hardening

## 1. Critical: HTTP 500 on every page (fixed)

`page_header()` in `app_private/bootstrap.php` called `user()`, a function that
no longer exists. A previous refactor removed the user-session system (see the
note at bootstrap.php line ~336) but left six call sites behind. Because
`page_header()` runs on every single page, the site returned a fatal
`Call to undefined function user()` and Apache served HTTP 500 sitewide.

Note this did NOT show up in a PHP syntax lint — `php -l` passes on all 60
files. Undefined functions are a runtime fault, not a parse fault.

### Call sites found and resolved

| File:line | Function | Was |
|---|---|---|
| bootstrap.php:923 `page_header()` | `user()` | Live blocker — fatal on every page |
| bootstrap.php:348 `is_admin()`    | `user()` x2 | Latent |
| bootstrap.php:563 `get_settings()`| `uid()`  | Latent — reachable via `money()` |
| bootstrap.php:570 `next_number()` | `uid()`  | Latent — used by tests/smoke.php |
| bootstrap.php:589 `audit()`       | `uid()`  | Latent |
| bootstrap.php:948 `owned()`       | `uid()`  | Latent |

### Changes
- Removed the dead `$u=user();` assignment in `page_header()`. `$u` was never
  read anywhere in the function.
- Added safe no-session stubs `user(): ?array { return null; }` and
  `uid(): int { return 0; }`. These match the post-refactor reality (access is
  verified email only) and keep every remaining caller on its existing
  no-user branch rather than throwing.

Verified: 0 undefined functions remain across all 60 PHP files.

## 2. Mobile and cross-device hardening

The existing CSS was already largely responsive (38 media queries, mobile
tables, reduced-motion and high-contrast support). V58 closes the remaining
gaps rather than rewriting what worked. All new rules are appended in a
clearly marked block at the end of `assets/style.css`.

| # | Gap | Fix |
|---|---|---|
| 1 | `.form` inputs (signup form) rendered below 16px, so iOS Safari zoomed the viewport on focus. Only `.gen-form` was protected. | 16px floor for every input/select/textarea under 820px and on any coarse pointer |
| 2 | Tap targets below the 44px Apple/Google guideline: `.li-remove` 32px, `.li-del` 34px, `.avatar-mini` 32px, `.tmpl-chip` 38px, `.gate-close` 38px | 44px minimum on coarse pointers; checkbox labels given a 44px hit area |
| 3 | No rules below 420px — long emails and invoice refs could force horizontal scroll on a 360px phone | 400px breakpoint with tightened type scale plus `overflow-wrap:break-word` on text elements |
| 4 | Landscape phones (short viewport) could trap the preview pane and the gate panel | Height-based landscape query releases max-height and makes the gate scrollable |
| 5 | Notched devices — header and footer ran under the rounded corners and home indicator | `env(safe-area-inset-*)` padding via `@supports` |
| 6 | iPad portrait (768-1024) collapsed the generator awkwardly | Explicit portrait-tablet layout |
| 7 | Very wide screens (1600px+) let text columns grow unreadably wide | Max-width cap on landing sections and hero |

## 3. Deployment packaging

- **Archive is flattened.** `index.php` sits at the archive root. The V57 zip
  wrapped everything in an `ign/` folder, which would have put the document
  root one level too high and produced a 403.
- **Removed `package.json`.** It was a decoy meant to make Hostinger import
  the repo as a full site. It does not work on the Node.js Web Apps addon
  (that flow only accepts Angular, Astro, Express, Fastify, Gatsby, Hono,
  NestJS, Next.js, Nitro, Nuxt, Parcel, React, React Router, Svelte,
  SvelteKit, Vite, Vue.js) and it only encourages the wrong deployment path.
  This is a PHP app and must go through PHP hosting.
- **Asset cache-buster bumped** `?v=57` -> `?v=58` so browsers pick up the new
  CSS instead of serving the cached stylesheet.
- All 7 `.htaccess` files and `.gitignore` are included.
- No secrets, no database, no `node_modules` in the archive.

## 4. Deploy

This app must NOT go through hPanel > Node.js / Web Apps. Use either:

- **Git:** site dashboard > Advanced > GIT. Push with GitHub Desktop or
  `git add . && git commit && git push` — GitHub's drag-and-drop upload
  silently drops dotfiles, which would remove the `.htaccess` protection on
  `app_private/`, `storage/`, `tests/`, `tools/` and `cron/`.
- **Manual:** upload the archive contents directly into `public_html`.

After deploying:
- Make `storage/` and `uploads/logos/` writable (0775).
- Keep `secrets.php` in `ign_private/` beside `public_html`, not inside it.
- Enable Force HTTPS in hPanel once the certificate is active.

## 5. Test results

| Test | Result |
|---|---|
| PHP syntax lint, 60 files | Pass |
| Undefined function scan | 0 found (was 2 functions / 6 sites) |
| Page execution, 42 web-facing pages | 42 pass, 0 fatal |
| Homepage render | 64,419 bytes valid HTML, 0 warnings or notices |
| CSS brace balance | 779 / 779 balanced |
| CSS comment balance | 141 / 141 balanced |
