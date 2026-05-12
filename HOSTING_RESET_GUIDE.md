# SLAMS Hosting Reset Guide

This guide assumes:

- DNS is managed at Hostinger
- web hosting and MySQL are on Namecheap shared hosting
- the deployable backend repo is `slams-mobile/`

## Recommended layout

Use one hosted CodeIgniter backend for both the website and the mobile app:

- `https://slams.cloud/` serves the public website and dashboards
- `https://slams.cloud/api/native/*` serves the mobile app API
- MySQL stays local to the same Namecheap cPanel account with hostname `localhost`

Do **not** make the mobile app connect directly to MySQL. On Namecheap shared hosting, remote MySQL is disabled and only local server-side access is expected.

## 1. Back up what exists now

Before you delete anything:

1. Export the current database from phpMyAdmin.
2. Download the current `public_html` directory.
3. Save the current `.env` from the server.

## 2. Fix DNS first

Your public DNS must point at the exact Namecheap shared-hosting IP shown in cPanel.

Keep only:

- `@` -> `A` -> `<Namecheap shared IP>`
- `www` -> `CNAME` -> `slams.cloud`

At the time of this audit, `slams.cloud` resolves to `162.0.229.135`. Compare that with the Shared IP shown in cPanel before you continue. If those do not match, correct DNS first and wait for propagation.

## 3. Clean the hosting account

After backups are complete:

1. Remove the current contents of `public_html`.
2. Keep the database only if you are certain it is the one you want to preserve.
3. If the database is already inconsistent, create a fresh database and import a clean dump instead of reusing the broken one.

## 4. Clone the correct repo in cPanel

The repo to deploy is `slams-mobile`, not `slams`, because `slams-mobile` contains:

- the website
- the shared authentication stack
- the `/api/native/*` endpoints required by the Expo mobile app

In cPanel Git Version Control:

1. Clone the GitHub repository.
2. Confirm the repository path.
3. Deploy using the checked-in `.cpanel.yml`.

The deployment file will:

- run `composer install` when Composer is available
- run `git lfs pull` when Git LFS is available
- copy `public/` into `public_html`
- generate a `public_html/index.php` that points back to the cloned repository path

## 5. Create the server `.env`

Create a real `.env` file in the cloned repository on the server. Do not commit it.

Minimum web settings:

```ini
CI_ENVIRONMENT = production
app.baseURL = 'https://slams.cloud/'
app.forceGlobalSecureRequests = true
app.indexPage =
```

Minimum database settings:

```ini
database.default.hostname = localhost
database.default.database = your_database_name
database.default.username = your_database_user
database.default.password = your_database_password
database.default.DBDriver = MySQLi
database.default.port = 3306
```

Important:

- The database hostname should stay `localhost`.
- The mobile app does **not** use a database endpoint.
- The mobile app calls the HTTPS backend URL instead.

## 6. Import database and run app checks

1. Import the chosen SQL dump into the Namecheap MySQL database.
2. If you rely on migrations, run them from the repository path.
3. Run:

```bash
php spark slams:audit-deployment
```

Before going live, the audit should stop showing:

- local/private base URLs
- `/public` in the base URL
- zero-byte placeholder media
- Git LFS pointer files deployed as media

## 7. Media-specific notes

The website currently depends on:

- tracked static images under `public/images/`
- runtime uploads in `public_html/images/` and `public_html/uploads/`
- a hero video at `public/images/uthm-aerial.mp4`

Important:

- The hero video is tracked with Git LFS.
- If the server cannot run `git lfs pull`, the deployed file will be only a small pointer file and the video will not play.
- If Git LFS is unavailable on the server, replace that video with a normal Git-tracked asset under 100 MB or sync it manually after deploy.

## 8. Website acceptance checks

Verify these URLs in a browser:

- `https://slams.cloud/`
- `https://slams.cloud/laboratories`
- `https://slams.cloud/contact`
- `https://slams.cloud/login`

Then verify individual assets:

- `https://slams.cloud/images/uthm-aerial.mp4`
- `https://slams.cloud/images/labs/<known-file>.png`
- `https://slams.cloud/images/pic/<known-file>.png`
- `https://slams.cloud/images/assets/<known-file>.png`

If the labs page still shows placeholder blocks for specific labs, that is a database content issue, not a DNS issue. Those lab rows do not currently have usable image paths.

## 9. Mobile app configuration

Only after the website/backend is stable:

1. Set `native-app/.env` for production:

```ini
EXPO_PUBLIC_API_BASE_URL=https://slams.cloud
EXPO_PUBLIC_EAS_PROJECT_ID=your-expo-project-id
EXPO_PUBLIC_APP_VARIANT=production
```

2. Confirm this works in a browser first:

```text
https://slams.cloud/api/native/health
```

3. Rebuild the mobile app.

The mobile app will only work when the hosted backend exposes `/api/native/*` successfully over HTTPS.

## 10. If cPanel deployment fails

Check the cPanel Git deployment log:

```text
~/.cpanel/logs/vc_*_git_deploy.log
```

Common causes:

- Composer is not available in PATH
- Git LFS is not installed on the server
- `.env` was not created on the server
- DNS points to the wrong IP
- the wrong repository (`slams`) was deployed instead of `slams-mobile`
