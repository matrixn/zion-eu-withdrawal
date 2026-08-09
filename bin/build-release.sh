#!/usr/bin/env bash
set -euo pipefail

plugin_slug="zion-eu-withdrawal"
project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
stage_root="$project_root/build/$plugin_slug"
dist_root="$project_root/dist"

cd "$project_root"
php -r "if (!preg_match('/Version:\\s*([0-9.]+)/', file_get_contents('zion-eu-withdrawal.php'), \$m)) { exit(1); } echo \$m[1];" >/dev/null
composer validate --no-check-publish

rm -rf "$stage_root"
mkdir -p "$stage_root" "$dist_root"

rsync -a --delete \
  --exclude='.git/' \
  --exclude='.deploy/' \
  --exclude='.dist/' \
  --exclude='.github/' \
  --exclude='.idea/' \
  --exclude='.vscode/' \
  --exclude='.wp-env.json' \
  --exclude='.phpunit.cache/' \
  --exclude='.phpunit.result.cache' \
  --exclude='.gitignore' \
  --exclude='bin/' \
  --exclude='DEVELOPMENT.md' \
  --exclude='CHANGELOG.md' \
  --exclude='vendor/' \
  --exclude='node_modules/' \
  --exclude='package.json' \
  --exclude='package-lock.json' \
  --exclude='phpunit.xml*' \
  --exclude='tests/' \
  --exclude='build/' \
  --exclude='dist/' \
  --exclude='deploy.ps1' \
  --exclude='*.log' \
  "$project_root/" "$stage_root/"

composer install --working-dir="$stage_root" --no-dev --prefer-dist --optimize-autoloader --no-interaction
find "$stage_root/vendor" -type d \( -name tests -o -name test -o -name docs \) -prune -exec rm -rf {} +
find "$stage_root/vendor" -type f \( -iname 'phpunit*.xml*' -o -name '.gitignore' -o -iname 'README*' -o -iname 'CHANGELOG*' \) -delete
rm -f "$stage_root/composer.lock"

version="$(php -r "preg_match('/Version:\\s*([0-9.]+)/', file_get_contents('zion-eu-withdrawal.php'), \$m); echo \$m[1];")"
archive_path="$dist_root/$plugin_slug-$version.zip"
rm -f "$archive_path"
(cd "$project_root/build" && zip -qr "$archive_path" "$plugin_slug")

archive_entries="$(unzip -Z1 "$archive_path")"
for required_path in \
  "$plugin_slug/composer.json" \
  "$plugin_slug/readme.txt" \
  "$plugin_slug/$plugin_slug.php"; do
  if ! grep -Fxq "$required_path" <<< "$archive_entries"; then
    echo "Fișier obligatoriu lipsă din arhiva de release: $required_path" >&2
    exit 1
  fi
done

echo "Release archive created: $archive_path"
