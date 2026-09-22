#!/bin/sh
# The installable plugin: dist/menudash.zip, with menudash/ as its only top-level folder
# (what WordPress expects), and no Finder clutter.
#   dev/build-zip.sh
cd "$(dirname "$0")/.." || exit 1
mkdir -p dist
rm -f dist/menudash.zip
zip -rq -X dist/menudash.zip menudash -x "*.DS_Store" -x "*/__MACOSX/*" -x "*/._*"
echo "wrote dist/menudash.zip ($(du -h dist/menudash.zip | cut -f1 | tr -d ' '), version $(sed -n 's/^ \* Version: *//p' menudash/menudash.php))"
