# BOU CSE Notes — MVP v1.5.0

A PHP 8.2+ / MySQL workspace for BOU CSE students. The production frontend is already built: no Node server, VPS, Docker, Redis, WebSockets or paid authentication service is required.

Already installed v1.0 or v1.1? Follow **[UPDATE_v1.1.md](UPDATE_v1.1.md)** to merge this update without changing your database. For the complete v1.5.0 package, read **[INSTALL_THIS_UPDATE.md](INSTALL_THIS_UPDATE.md)**.

Start with **[LOCALHOST_AND_HOSTING.md](LOCALHOST_AND_HOSTING.md)** for Windows/XAMPP, a PHP-only local option, and shared/cPanel hosting instructions.

## Included

- Registration, email verification, sign-in/out, remembered login, password reset, password change, verified email change and sign-out-all.
- Overview reconstructed from the supplied HTML, with the original code table, course cards, logo, icons, gradients, rounded cards and productivity rail.
- Personal course/module tracking, lab goals, timezone-aware streaks, personal points/tiers, approximate active coding time, and storage requests.
- CodeMirror editor with syntax highlighting, line numbers, indentation, undo/redo, full screen, copy, download, 3-second autosave and last 20 code versions.
- Markdown notes, safe preview, pinned notes, subjects and tags.
- Private file uploads, protected downloads, image previews, display-name changes, moving and attachments.
- Projects, folders (up to five levels), metadata search and content search, filters, pagination and sorting.
- Favorites, soft deletion, restoration, permanent deletion and scheduled cleanup.
- Revocable read-only unlisted links, optional expiry, direct sharing with verified accounts and Shared With Me.
- Notifications, profile/preferences, initial avatars, metadata/code/note export and deletion requests.
- Admin: recent users, suspend/activate, storage limits, support address, share revocation, in-app announcements and email logs.
- JSON, Markdown, Base64, URL, text count, case, timestamp and isolated regex tools.
- Locally bundled fonts, compiled reference Tailwind styles, local CodeMirror and PHPMailer. No runtime CDN requirement.

## MVP boundaries

This is a first release for controlled student testing, not a claim of an independent security audit or production operation at scale. Finish the deployment checks in the guide before inviting students.

- Public discovery, competitive leaderboards, hosted lessons/video delivery, challenges, results, collaboration, execution and offline/PWA functionality are deferred. Courses are personal progress trackers with optional external material links. Points and tiers describe personal progress. Storage upgrades are administrator requests, not paid subscription checkout.
- Files allowed initially: JPEG, PNG, WebP, PDF, TXT, Markdown and CSV. Office/ZIP files are disabled. PDF and document viewing uses download; image previews work in the app.
- The supplied workspace logo appears in the profile card; photo uploads are not included. UI is English; Bengali note content and typography are supported. There is no full Bengali UI translation.
- Note formatting is Markdown; images are file attachments rather than inline image embeds. Shared parent items do not share attachments automatically.
- Default visibility is always private; sharing is explicit. Public profile/discovery settings are deferred.
- Sessions can be invalidated together; individual device/session listings are deferred. Remembered login is one token per account, replaced by the latest remembered sign-in.
- Admin lists show up to 200 recent accounts and 100 recent email/share records. Global workspace lists are paginated; full admin pagination is a later improvement.
- Announcements and storage warnings are in-app. No broadcast-email queue. SMTP is synchronous and must be configured; failures are logged without credentials or secret tokens.
- Account deletion is a request, processed manually by the administrator. No automatic destructive account erasure endpoint.
- Code/note/file metadata export does not bundle uploaded binary files or attachments; download those separately.
- Trash is purged only if the maintenance cron is configured. Stored files in Trash continue to count toward quota.
- Syntax highlighting for common languages is bundled; assembly/other content falls back to plain text.

## Source layout

| Path | Purpose |
| --- | --- |
| `app/bootstrap.php` | Config, database, sessions, CSRF, rate limiting, mail, shared helpers |
| `app/auth.php` | Authentication and email-token flows |
| `app/study.php` | Personal course/goal validation, activity/streak summaries |
| `app/items.php` | Resources, organization, uploads, attachments and versions |
| `public/index.php` | Application HTML shell |
| `public/api.php` | JSON API with ownership/role checks |
| `public/download.php` | Authenticated or authorized-share downloads |
| `public/paths.php` | Bootstrap path for flexible shared hosting |
| `public/assets/app.js` | Routes, forms and user interactions |
| `frontend/app.css` | Design tokens, responsive layout and Tailwind input |
| `public/assets/app.css` | Compiled production styles |
| `database/schema.mysql.sql` | MySQL/MariaDB schema |
| `bin/` | Install, admin creation, maintenance and frontend build |
| `storage/` | Private uploads, local test email and optional local SQLite DB |
| `tests/` | API and browser regression checks |
| `docs/` | Scope, architecture and validation notes |

## Continue designing

The overview HTML and design guide supplied for this task remain the visual references. The implemented shell carries the navy palette, cool gradients, dashed borders, rounded cards, two-column content and right utility rail into the other routes. The mobile version uses a bottom navigation bar and a menu for secondary routes.

To integrate future page designs, edit the corresponding page renderer in `public/assets/app.js`, reuse the component classes in `frontend/app.css`, then run `npm ci` and `npm run build`. Preserve the JSON contracts and ownership checks. No frontend build is needed merely to run the supplied package.

## Development checks

- PHP syntax: `php -l public/api.php` (repeat for changed PHP files).
- JavaScript syntax: `npm run check`.
- Assets: `npm ci` then `npm run build`.
- Integration: run a local PHP server and `python tests/api_test.py` against an **isolated local database with local mail mode**. This creates users/items. Never run it against production.
- Browser: install Playwright separately, seed the documented local QA account or change the credentials in `tests/browser.cjs`, and run that script against localhost. This script is a development check, not a production dependency.

See `docs/VALIDATION.md` for the actual completed checks and limitations.

## Temporary sharing and media galleries (v1.2.0)

See [TEMP_SHARING.md](TEMP_SHARING.md). New → Temporary Share lets guests create collaborative pages for files, text and code with expiry and QR sharing. Files display inline image/video galleries and support multi-file drag-and-drop. No database migration required. Configure the included cleanup task for removal without traffic.

## Leaderboard and gamification (v1.3.0)

See [GAMIFICATION.md](GAMIFICATION.md). Includes opt-in rankings, durable server-awarded XP, levels, six achievements, daily sprints and persistent cheers. The overview shares the same XP ledger.

## Progress feedback and changelog (v1.4.0)

See [FEEDBACK_AND_CHANGELOG.md](FEEDBACK_AND_CHANGELOG.md). Uploads show actual transfer progress and server processing, with retry for remaining files. Features & Changelog is available to signed-in users and guests.

## Live notifications and password controls (v1.4.1)

See [NOTIFICATIONS_AND_PASSWORDS.md](NOTIFICATIONS_AND_PASSWORDS.md) for unread badges, individual read controls, automatic admin updates and password show/hide controls.

## File preview gallery (v1.5.0)

See [FILE_GALLERY.md](FILE_GALLERY.md) for supported previews, filters/sorting, hover and context-menu actions, and compact notification controls.
