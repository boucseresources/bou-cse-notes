# BOU CSE Notes — Super Admin CMS

## Install once

Extract this cumulative update ZIP into your EXISTING local repository, replacing matching files. Keep other project files and include `.github` / `public/.htaccess`. Commit and push to `main` yourself. Wait for Deploy to Hostinger to finish successfully, then reload https://in.aloskill.com.

The new `20261011_cms.php` migration creates CMS tables and default branding. It preserves existing users, content, quotas, photos and uploads. Earlier migrations are unchanged. Deployment runs migrations automatically; manual installation requires `php bin/migrate.php` from the private app folder. Copy private `app`/`bin` changes into the private app directory and public files into the web folder, retaining `paths.php` and your existing config.

This is an update package, not a standalone application. No deployment or commit was made for you. It contains no production credentials, configuration, database or user uploads.

## Open it

Sign in to your Super Admin account → **Super Admin** in the sidebar/account menu. The existing `#admin` route is now the CMS dashboard. Existing user controls are at `#admin-users`; global settings/reports remain at `#admin-system`. Legacy administrator accounts retain their existing super-admin access. Teachers and students cannot use CMS administration APIs.

## Change content without a GitHub push

- **Site & login:** edit website name, tagline, login headline/description and support email. Select the original BOU logo or an image uploaded to the library. The selected logo is used for branding and the browser icon. Your profile photo remains separate.
- **Pages:** add/edit guides, FAQs, useful information or policies. Use the formatting buttons and live Markdown preview. Choose draft, published or archived, and everyone vs approved members. Drafts can be previewed by admins. To restore an archived page, edit it and choose draft/published. Page links use `#page?slug=your-page`.
- **Menus:** add a label and external HTTP/HTTPS URL, a supported workspace route, or an existing CMS page route. Move links up/down, choose their audience and save. Links appear in the workspace sidebar/mobile menu and login/public page navigation. Draft/archived CMS page links stay hidden from visitors. Add a page to Menus after publishing if you want it in navigation.
- **Image library:** upload JPG/PNG/WebP logos and page images (5 MB and 4096 pixels maximum, subject to PHP hosting limits; 100 MB total CMS budget). Copy an image's Markdown into a page, on its own line. These are PUBLIC website images. Private student files and profile photos continue using their separate protected storage.
- **Notices:** save a dashboard banner, enable/disable it, or send an in-app announcement to all active, verified, approved members. Announcements are limited to three per hour; they do not send mass email. Group-specific teacher announcements remain in Teacher Sharing.
- **Activity:** see recent CMS changes and the administrator who made them.
- **Accounts & platform controls:** open approvals, teacher permissions, per-user quotas, guest limits and existing reports through dashboard shortcuts.

Click Save / Publish, then refresh another open browser tab to see the change. CMS content is stored in the database; uploaded CMS images live under private `storage/cms` and are served through a validated public image endpoint. They survive later GitHub pushes and normal code deployments.

The editor escapes raw HTML; pages cannot execute embedded HTML/scripts. There are up to 500 pages and 20 menu links. Page and site settings include revision checks: if another tab/admin already changed the record, reload before saving to avoid overwriting their edits. Used images cannot be deleted while referenced by the logo or any CMS page, including archived pages.

This CMS manages content and settings. New application functionality or changes to the underlying layout/components still require code. This release adds no plugin installer or arbitrary HTML editor. Cloudinary remains unconfigured; uploads use existing Hostinger storage.

## Validation

PHP 8.2 HTTP/SQLite integration covers CMS roles/CSRF, configuration conflicts, draft/published/member pages, archives, filtered menus, unsafe URL rejection, public image upload/read/delete protection, in-app announcements and audit entries. Browser checks cover desktop/mobile administration, branding updates, publishing, menus, image upload, dashboard notices, existing accounts controls, guest page navigation and inert raw HTML. Live Hostinger/MySQL has not been exercised here.
