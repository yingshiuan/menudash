#!/bin/sh
# Parser, photo-matching and SVG icon safety tests, in WordPress Playground's PHP (needs only Node).
#   dev/test.sh            PHP 8.3
#   dev/test.sh 7.4        another PHP version
cd "$(dirname "$0")/.." || exit 1
status=0
for t in parser-test.php svg-test.php; do
	echo "== dev/$t"
	npx -y @wp-playground/cli@latest php --php="${1:-8.3}" --verbosity=quiet --mount="$PWD:/repo" -- "/repo/dev/$t" 2>&1 || status=1
done
exit $status
