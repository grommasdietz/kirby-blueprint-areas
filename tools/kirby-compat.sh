#!/bin/sh
# Run the Kirby compatibility suite against one Kirby version in an isolated copy of the
# repository, so the regular vendor/ directory stays untouched.
# Usage: sh tools/kirby-compat.sh <kirby-version>, for example 5.6.0 or ^5.6
set -eu
version="${1:?Usage: sh tools/kirby-compat.sh <kirby-version>}"
label=$(printf '%s' "$version" | tr -c 'A-Za-z0-9.' '-')
work="build/compatibility/kirby-$label"
rm -rf "$work"
mkdir -p "$work"
rsync -a --exclude /.git --exclude /build --exclude /node_modules --exclude /vendor \
  --exclude /playground/vendor --exclude /composer.lock ./ "$work/"
php tools/kirby-compat-manifest.php "$version" > "$work/composer.json"
cd "$work"
composer update --with-all-dependencies --prefer-dist --no-progress --no-interaction --no-scripts
composer show getkirby/cms | sed -n '1,4p'
vendor/bin/phpunit --configuration phpunit.compat.xml.dist --testsuite "Kirby compatibility" --display-all-issues
