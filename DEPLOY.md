# Deploying CLAPET ASSET MANAGEMENT UG - SMC

Production setup for **clapetassets.com** (Git auto-deployment from `main` into `public_html`).

The site has no `.env` file. Its "environment" is made of three things that are never stored in Git:
the PHP settings on the server, the `storage/` data folder, and the admin account.

## 1. PHP on the server (hosting panel → Advanced → PHP Configuration)

- PHP **8.1 or newer** (8.2 recommended)
- Extensions on: `pdo_sqlite`, `mbstring`, `iconv`, `fileinfo`, `json`
- Leave `display_errors` off (the site also turns it off itself)

## 2. HTTPS

Hosting panel → SSL → install the free certificate, then switch on **Force HTTPS**.
`.htaccess` also redirects HTTP to HTTPS on the live domain (it skips `localhost`).

## 3. First deployment

1. Push to `main` (auto-deployment copies the repository into `public_html`).
2. Folders `storage/enquiries`, `storage/uploads` and `assets/img/uploads` come with the repo.
   Everything else inside `storage/` is created on first use.
3. Make sure `storage/` and `assets/img/uploads/` are writable by the web server (permissions 755, or 775 if asked).

## 4. Create the admin account (no SSH needed)

1. In the hosting File Manager, create a file named `storage/setup.key` containing one long random line
   (use the key you were given, or any 32+ character random string).
2. Open `https://clapetassets.com/admin/setup.php`.
3. Enter the key, your admin email and a strong password (10+ characters).
4. The key file is deleted automatically and the page disappears (404).

With SSH you can instead run: `php admin/setup.php you@example.com "a-strong-password"` (also resets a login).

## 5. Configure the site (all inside `/admin/`)

- **Contact & credentials**: confirm phone, email, WhatsApp, address and hours. Tick *Verified* only for credentials you hold certificates for.
  Turn *Email me when a form is submitted* on once mail works (create a mailbox in the hosting panel; check spam on the first test).
- **Photos**: replace the Creative Commons stand-in photos with your own when ready (credits disappear when you clear them).
- **Website text, Projects, Listings, Testimonials, Tenders, FAQs**: edit and publish.
- **System check**: must show **0 to fix**. Run it after every deployment.

## 6. Smoke test after each deployment

1. Open the home page and click through the menu.
2. Submit the project brief form with test data; confirm it appears under **Enquiries** and downloads from **Forms & downloads**.
3. Open **Visitor analytics** (your own admin visits are not counted).
4. Open `https://clapetassets.com/robots.txt` and `/sitemap.xml`.

## 7. Backups and updates

- Data lives only in `storage/` and `assets/img/uploads/`. Back both up (hosting backups, or download them from File Manager).
- Never edit tracked files on the server: the next auto-deployment would overwrite them. Change code locally, commit and push.
- Rebuilding pages: `python tools/build.py` regenerates the `.php` pages from `tools/build.py`. Commit the result.
- Data and secrets are ignored by Git (`.gitignore`), so deployments never overwrite enquiries, settings, text edits or the admin login.
