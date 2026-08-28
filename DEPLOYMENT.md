# Deploying BizManager to Z.com (or any shared hosting)

BizManager installs the way a WordPress site does: upload the files, open the
site in a browser, and a wizard at `/install` takes care of the rest. You
never need to edit `.env` by hand, import a `.sql` file, run Composer or
Artisan commands, or ask your host to change any server configuration.

## 1. Upload the files

Upload the entire project (everything in this repository) to your hosting
account.

- **If your host lets you point the domain's document root at a subfolder**
  (most cPanel-style hosts, including typical Z.com shared hosting plans),
  point it at the `public/` folder. This is the normal, recommended setup.
- **If your host only allows the document root to be the account's root
  folder** (some lower-tier shared hosting plans), just upload everything to
  that root folder as-is. The `index.php` and `.htaccess` files at the
  project root hand requests off to `public/` automatically — no manual
  reconfiguration needed either way.

## 2. Open the site

Visit your domain in a browser.

If your hosting account doesn't meet a hard requirement — wrong PHP version,
a missing PHP extension, the `vendor/` folder wasn't included in the upload,
or a folder PHP needs to write to isn't writable — you'll see a plain
diagnostic page explaining exactly what's wrong and how to fix it (usually a
one-click PHP version switch or extension toggle in your host's control
panel), instead of a generic server error. This check runs before anything
else, so it always has something useful to say even if the app itself can't
start yet. Fix whatever it lists and reload the page.

**Requires PHP 8.3 or higher.** This isn't an arbitrary choice — it's the
actual minimum the underlying Laravel framework version requires, so it
can't be lowered without using an older, unsupported framework version. Most
modern shared hosts (Z.com included) offer PHP 8.3 as a dropdown in the
control panel (often called "PHP Version" or "MultiPHP Manager"); very
low-end or free hosting plans sometimes cap out on an older PHP version,
which the diagnostic page above will tell you plainly rather than leaving
you to guess at a generic error.

Once your server passes that check, you'll be sent straight to `/install`
automatically. The installer will:

1. **Check your server** — PHP version, required extensions, and that
   `storage/`, `bootstrap/cache/`, and `.env` are writable. Fix anything
   flagged red (usually a file permissions issue, or an extension to ask
   your host to enable) and reload the page.
2. **Ask for your database details** — host, port, database name, username,
   and password for the MySQL/MariaDB database your host gave you. The
   connection is tested before anything is saved. Once it succeeds, the
   installer writes your `.env` file and runs the database setup for you.
3. **Create your Super Admin account** — the platform-level account used to
   manage the whole system (plans, businesses, promotions). Individual food
   businesses sign up for their own accounts afterwards from the normal
   registration page.
4. **Finish** — you're taken to the login page, ready to go.

## 3. Reinstallation is blocked automatically

Once installation completes, `/install` redirects away on its own — there's
no separate step to "lock" or disable it, and no file to delete afterward.

## Notes for the technically curious

- Before `.env` exists, the very first request bootstraps a minimal one
  (fresh `APP_KEY`, file-based sessions/cache so nothing touches the
  database yet) — this is what lets the installer's own pages render at
  all before a database is configured.
- Whether the app has been installed is tracked by
  `storage/app/installed.lock`, not by anything in the database — it exists
  independently of whatever database you eventually connect.
