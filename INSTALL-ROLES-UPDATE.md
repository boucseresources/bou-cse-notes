# BOU CSE Notes — roles, sharing and automatic database updates

This is an UPDATE for the existing boucseresources/bou-cse-notes repository and in.aloskill.com installation. Merge these files into the project root; it is not a complete standalone application.

## Apply with your existing GitHub → Hostinger workflow

1. Download and extract this ZIP.
2. Copy ALL extracted folders/files into your local `bou-cse-notes` project folder (the folder containing `package.json`). Replace matching files, and keep every other existing file. Include the `.github` folder; it can be hidden by your file browser.
3. Keep your current `config/config.php`, database and `storage` files. None of them are included in this update.
4. In a terminal inside that project folder:

```sh
git status
git add app bin public deploy tests .github INSTALL-ROLES-UPDATE.md
git commit -m "Add account approvals, teacher sharing and automatic migrations"
git push origin main
```

5. Open your repository's **Actions → Deploy to Hostinger**. Wait for the run to turn green, then reload the website. The workflow still uses your five existing HOSTINGER secrets and production environment.

No new database credentials or GitHub secrets are needed if the existing deployment is already working. The Actions workflow builds assets, checks PHP/JavaScript, runs the existing session regressions and isolated role API tests, then deploys.

The server applies additive, versioned migrations using its EXISTING private configuration before copying application code. A migration failure stops that deployment before copying. Successful migrations are skipped on later pushes; do not edit an already-applied migration. Add a new migration file for a later database change. Existing database data, private config and uploaded files are preserved. Code copying uses the existing rsync deployment; it is not an atomic release swap.

Existing hosting folders:
- Private application: `~/domains/aloskill.com/bou-cse-notes`
- Public website: `~/domains/aloskill.com/public_html/in`

## If you manually upload instead of pushing

Back up the database, copy private `app`/`bin` updates to the private application folder, then run this from Hostinger SSH BEFORE opening the updated website:

```sh
cd ~/domains/aloskill.com/bou-cse-notes
php bin/migrate.php
```

Copy the CONTENTS of `public` to `~/domains/aloskill.com/public_html/in`, preserving the existing `paths.php`. Do not upload the entire project or private application/config into the public folder. The recommended process above avoids repeating this manual installation.

## Where to use it

| Page | Address |
| --- | --- |
| Super Admin approvals, permissions, individual quotas and storage requests | https://in.aloskill.com/#admin |
| Existing global configuration, email logs and share links | https://in.aloskill.com/#admin-system |
| Teacher group membership | https://in.aloskill.com/#groups |
| Teacher sharing and notifications | https://in.aloskill.com/#teacher |
| Member shared resources | https://in.aloskill.com/#resources |
| Anonymous code/text/file sharing, link and QR | https://in.aloskill.com/#drop |

These links are also available in the workspace/account menus; anonymous sharing has a prominent button on the login page.

## Account and sharing behavior

- Existing administrators become Super Admins; existing accounts remain approved. Super Admin accounts cannot be suspended or demoted through the account editor.
- New registrations request Student or Teacher access. Email verification AND Super Admin approval are required before opening the private workspace. Users cannot choose Super Admin during registration.
- Super Admin → Manage lets you approve/reject, choose Student/Teacher, suspend/reactivate, set a total storage quota/per-file limit and enable/disable teacher publishing or group management. Blank quota overrides use the global defaults. Lowering a quota never deletes existing files; further uploads must fit the limit. Hosting PHP/server upload limits still apply.
- Members can request a higher TOTAL quota on the Storage page. Super Admin approves or rejects the tracked request. Approval never lowers a quota that was already raised manually.
- Teachers first save code/notes or upload a file to their workspace, then choose it on Teacher Sharing. They can also send an announcement without an attachment.
- Teacher audiences: all approved members, selected groups, or selected members. Notifications are persistent IN-APP notifications, not bulk emails. Overlapping groups produce one notification per member. Group/member recipients are captured when publishing; changing group membership later does not change an existing post's audience. All-member posts are also visible to future approved members.
- Teachers manage their own groups. Super Admin can manage all groups. A teacher or Super Admin can revoke the appropriate share; denied/rejected/suspended authors' resources cannot be accessed.
- Anonymous sharing requires no account. Super Admin controls enable/disable, file/text/page/global storage limits, entries per page, pages per hour and maximum expiry. Disabling it blocks anonymous access/downloads until enabled again. Links and QR appear immediately after creating a sharing page. This update uses the existing Hostinger file storage.

## Validation performed

Real PHP 8.2 HTTP/SQLite integration covered protected roles, account approval/verification, permissions, group ownership, audience access, notification deduplication, downloads/revocation, quota enforcement, storage requests, guest limits and CSRF. The existing 43 session-client tests passed. Browser tests covered the admin quota editor, teacher group/share flow, student code access/mobile resources and anonymous code/QR flow. A mocked deployment verified migrations run before copying, failed migrations stop copying, and config/uploads are preserved.

The live Hostinger deployment and a live MySQL migration have not been run by this ZIP's author. GitHub Actions will run the checks again on your push; check its result before considering the site updated.
