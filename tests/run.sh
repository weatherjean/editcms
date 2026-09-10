#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
version=${1:-test}

while IFS= read -r -d '' file; do
  php -l "$file" > /dev/null
done < <(find _edit/core _edit/api _edit/admin-api tests -name '*.php' -print0)
php -l router.php > /dev/null

bash build.sh "$version"
node --test tests/content-list.mjs
for test in captcha public-boundaries content-correctness auth-sessions setup-permissions html-config example-config; do
  php "tests/$test.php"
done
php tests/public-boundaries.php admin-api
for test in content-integration upload-boundaries deployment-boundaries email_integration recovery-integration setup-integration config-integration; do
  python3 "tests/$test.py" "dist/_edit-$version.zip"
done
python3 tests/release-archive.py "dist/_edit-$version.zip"

python3 tests/release-publishing.py
