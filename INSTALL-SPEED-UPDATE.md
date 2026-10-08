# BOU CSE Notes — faster loading update

This cumulative update includes the earlier roles, approvals, teacher sharing and anonymous sharing changes. Merge it into the EXISTING bou-cse-notes repository; do not delete other files. It is not a standalone application.

## Install

1. Extract this ZIP. Copy all contents into the local project folder containing `package.json`, replacing matching files. Include `.github` and the public `.htaccess` file.
2. Commit your changes and push to `main`.
3. Wait for GitHub → Actions → Deploy to Hostinger to turn green. The existing workflow builds the optimized browser bundle, runs checks, applies migrations and copies the code.
4. Reload in.aloskill.com. A hard refresh once after installing is useful when testing the new release.

The ZIP does not contain your private configuration, database, uploaded files or account credentials. Existing server config/uploads remain in place. The previous `20261008_roles_groups.php` migration is unchanged. A NEW `20261008_speed.php` migration creates missing game tables and adds query indexes, preserving existing data and XP.

Manual uploads require `php bin/migrate.php` from the private application folder. Copy `app`/`bin` to the private folder and the CONTENTS of `public` to the subdomain's public folder, preserving `paths.php`. Use the GitHub workflow to build new assets automatically. If a prebuilt bundle does not match your source files, the entry page falls back to the source module rather than running a stale bundle.

## What changed

- Login no longer downloads the code editor, editor CSS, workspace templates or QR generator. CodeMirror loads when opening a code editor; QR loads when generating a QR; templates load for the private workspace.
- Boot configuration is carried by the uncached HTML response, removing the separate `bootstrap.php` request from startup.
- Production build produces a minified JavaScript entry and lazy chunks with content-based filenames. Bundled file previews remain in their original module location so PDF worker/font URLs keep working.
- Stable hashed assets have long-lived browser caching. Optional server gzip compression is enabled when supported. Authenticated HTML, API results and private downloads remain uncached.
- Switching pages no longer adds a duplicate notification refresh. Polling still checks every 3 seconds while the tab is visible/online. Unchanged polls fetch no notification bodies; read/delete/new notification changes trigger fresh items.
- Read-only authenticated API work releases the PHP session lock after checking access and saving activity time, so it does not keep that session locked throughout dashboard/database work. Uploads and authentication writes keep their existing session handling.
- Global settings are fetched once per request; the account's already-loaded permissions and limits are reused where possible. Settings cache is request-local and administrator writes update it immediately.
- Dashboard queries select card/table metadata instead of fetching full code/note bodies. Storage uses a small dedicated API instead of building a complete dashboard.
- Game table initialization moved out of page requests into the deployment migration. Indexed queries help recent items, activity, notifications and shared resources.
- Successful API responses include `Server-Timing` with database query count and measured database time for diagnosis.

## Validation and observed results

In the isolated sample fixture, session checks went from 7 SQL queries to 3; dashboard reads from 29 to 20. The dedicated storage endpoint used 4 queries. These are query counts, not claims about live page speed. The initial login used 3 JavaScript requests with the production build and did not request the editor, QR or workspace template chunks.

PHP 8.2 HTTP/SQLite integration passed for all role/approval/quota/sharing/CSRF flows and optimized notification read/delete behavior. Browser tests passed for admin quota updates, teacher groups/sharing, student code access/mobile resources, anonymous code/QR and lazy CodeMirror. The existing 43 session transport tests passed. Migrations applied once and the second run skipped them.

Live Hostinger/MySQL performance has not been measured here. Hosting CPU/database load, geographic latency and uploaded file sizes can still affect speed. Heavy all-user leaderboard recalculation remains a separate scaling concern; this patch does not introduce stale permission/data caching or promise instant loading.

## Diagnose if it remains slow after deployment

In Chrome, press F12 → Network. Open a slow page and select its `api.php` request. Look at **Timing → Waiting for server response** and the **Server-Timing** metrics in Headers/Timing. High DB duration points to database work; high overall waiting time with low DB duration can include PHP/session/hosting/network overhead. Slow asset transfers are a different part of the load. Keep these requests uncached for the diagnosis.

Test a first visit and a repeat visit; browser cache makes their results different. Record the slow page and request's Timing rather than comparing only the loading spinner. Do not change domain DNS or migrate hosting solely on the basis of that spinner.
