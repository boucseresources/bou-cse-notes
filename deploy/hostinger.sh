#!/usr/bin/env bash
set -euo pipefail
source_dir=$(cd -- "${1:?Source directory required}" && pwd)
hosting_home=${DEPLOY_HOME:-$HOME}
private_dir="$hosting_home/domains/aloskill.com/bou-cse-notes"
public_dir="$hosting_home/domains/aloskill.com/public_html/in"
[[ -d "$private_dir/app" && -d "$public_dir" && -f "$private_dir/config/config.php" ]] || { echo 'Existing hosting layout/config missing. Refusing deployment.' >&2; exit 1; }
[[ -d "$source_dir/app" && -f "$source_dir/public/index.php" && -f "$source_dir/vendor/autoload.php" ]] || { echo 'Incomplete release.' >&2; exit 1; }
command -v rsync >/dev/null
# Run restart-safe migrations using the existing private configuration.
# Abort BEFORE deploying new application code if migration fails.
command -v php >/dev/null
[[ ! -e "$source_dir/config" ]] || { echo 'Release must not contain config.' >&2; exit 1; }
ln -s "$private_dir/config" "$source_dir/config"
php "$source_dir/bin/migrate.php"
# Code only; configuration and uploaded files stay on the server.
for directory in app bin vendor; do
  mkdir -p "$private_dir/$directory"
  rsync -a --no-owner --no-group "$source_dir/$directory/" "$private_dir/$directory/"
done
rsync -a --no-owner --no-group --exclude=paths.php "$source_dir/public/" "$public_dir/"
printf "<?php\nrequire __DIR__ . '/../../bou-cse-notes/app/bootstrap.php';\n" > "$public_dir/paths.php.next"
mv "$public_dir/paths.php.next" "$public_dir/paths.php"
echo 'Code deployed. Configuration, storage and database preserved.'

