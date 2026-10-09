# BOU CSE Notes — mobile login, uploads and profile photos

This ZIP is a cumulative update for your EXISTING repository. Copy its contents into the local project, replacing matching files. Keep other project files. Include `.github` and `public/.htaccess`.

## Install through GitHub → Hostinger

1. Extract the ZIP and copy all contents into your existing `bou-cse-notes` project folder (the folder with `package.json`).
2. Commit and push to `main` yourself.
3. Wait for GitHub → Actions → Deploy to Hostinger to finish successfully. The workflow builds browser assets and runs `php bin/migrate.php` before publishing.
4. Open https://in.aloskill.com and refresh once. Check Settings → Username and try signing in with that username.

Keep your existing Hostinger configuration, database, uploaded files, `public/paths.php` and GitHub deployment secrets. They are not included in this ZIP. No commit or deployment was made for you.

For manual deployment: copy `app` and `bin` into the private application folder, copy the contents of `public` into the subdomain public folder, and run `php bin/migrate.php` from the private folder. Preserve the existing `paths.php` and config. Do not upload private application files into the public folder.

## Changes

- Login accepts either username or email, including existing accounts. Existing usernames are generated from the email prefix, with a suffix when necessary. Find or change yours in Settings. New registrations can choose a username: 3–24 letters, numbers or underscores, starting with a letter. Usernames are unique and case-insensitive.
- Remember me keeps your session and remembers the login identifier on that device. It does not store the password in browser storage.
- File selection uses a visible native picker with a touch target on mobile. Multiple-file uploads retain progress and error feedback. The app checks both your account file limit and PHP hosting upload limits, with clearer errors when the host rejects a file.
- MOV videos, M4A and AAC audio have additional supported MIME mappings. Unsupported formats still receive an error rather than bypassing validation.
- Settings includes profile photo upload, replacement and removal. Photos are resized on the device to at most 512 pixels, then validated server-side and stored privately. JPG, PNG and WebP are recommended; HEIC depends on the browser being able to decode it. Photo previews appear in Settings and the account menu. Other profile fields remain editable.
- Oversized uploads return an upload-limit error instead of incorrectly reporting an expired session.
- Earlier roles, approvals, quotas, teacher group sharing, anonymous sharing/QR, previews, notification and loading-speed updates are included.

The new `20261009_mobile_identity.php` migration adds usernames and profile photo metadata without deleting existing accounts or uploads. The earlier migrations remain unchanged. Uploaded files still use Hostinger storage; Cloudinary integration needs your provider configuration and is not included.

## Verification

PHP 8.2 HTTP/SQLite integration passed for role permissions, approvals, quotas, sharing, notification behavior, username/email login, username conflicts and changes, profile photo upload/removal and access control, file uploads and invalid/oversized photo rejection. All 43 session transport regressions passed.

An emulated 390-pixel touch/mobile Chromium browser passed two-file upload, username changes, photo upload/render/removal, Settings overflow checks, Mark All as Read, and logout followed by username login in the same browser. This is not a physical iPhone/Android or live Hostinger/MySQL test.
