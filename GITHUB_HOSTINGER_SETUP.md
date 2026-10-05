# GitHub → in.aloskill.com

This prepared copy excludes config/config.php and all live storage. Supplied vendor dependencies and browser assets are included. GitHub Actions rebuilds browser assets before deployment. No install/reset/migration/database command is run automatically.

## One-time setup

1. Cancel Hostinger's native Git import. It copies the repository into one directory; this app currently uses two.
2. Extract this ZIP locally. Use GitHub Desktop: File → Add local repository → select the extracted folder → create a repository here if prompted. Commit, then Repository → Repository settings → Remote: https://github.com/boucseresources/bou-cse-notes.git . Push main. The repository is empty, so this creates its first branch. Use your boucseresources GitHub account. Do not upload the ZIP itself to GitHub. Upload the entire extracted tree, including .github.
3. Hostinger: website dashboard → Advanced → SSH Access. Enable access and note host, port and username. Generate a dedicated SSH key on your computer and add its PUBLIC key in Hostinger's SSH keys section. Keep the private key private.
4. On Windows PowerShell: ssh-keygen -t ed25519 -f "$env:USERPROFILE/.ssh/bou-hostinger" -C "GitHub deployment" . Use an empty passphrase for this dedicated automation key. Public key ends in .pub; private key has no extension.
5. Connect once using SSH and verify the displayed server fingerprint against Hostinger's trusted information before accepting it. Copy the resulting known_hosts entry for this host/port. Do not blindly accept an unverified fingerprint.
6. GitHub repository → Settings → Secrets and variables → Actions → New repository secret. Add:
   - HOSTINGER_SSH_HOST: SSH host/IP
   - HOSTINGER_SSH_PORT: SSH port
   - HOSTINGER_SSH_USER: hosting SSH username
   - HOSTINGER_SSH_KEY: full private key, including BEGIN/END lines
   - HOSTINGER_KNOWN_HOSTS: verified known_hosts entry for that host and port
7. GitHub → Actions → Deploy to Hostinger → Run workflow → main. The initial push will fail safely while these secrets are missing; rerun after configuring them.

## Folder checks

The script assumes the SSH account home contains these existing folders:

- domains/aloskill.com/bou-cse-notes (private)
- domains/aloskill.com/public_html/in (public)

It refuses to run if the live config or these folders are missing. Confirm them in SSH before the first run; adjust deploy/hostinger.sh if the SSH layout differs. It writes the public paths.php to load ../../bou-cse-notes/app/bootstrap.php.

## Future updates

Commit source changes and push main. Actions builds, checks PHP syntax, then updates code. Monitor its log, then open https://in.aloskill.com and test login/upload. Storage, configuration and the MySQL database remain on Hostinger. Schema changes require separate reviewed migrations. File copies are not an atomic release, so a brief mixed-version window can occur. No files are deleted automatically; remove obsolete code deliberately. Keep a Hostinger backup before the first deployment. Native Git auto-deployment should stay disabled to avoid two competing deployment methods.
