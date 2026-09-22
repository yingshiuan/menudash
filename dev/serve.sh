#!/bin/sh
# A local WordPress with MenuDash and the sample menu, for trying it out:
#   dev/serve.sh             then open http://127.0.0.1:9400/?pagename=menu
#   dev/serve.sh 9401        on another port
# Logged in as admin (password "password"); the upload page is Dashboard -> MenuDash.
# Uploaded menus and photos are kept in dev/playground-uploads between runs.
cd "$(dirname "$0")/.." || exit 1
PORT=${1:-9400}
mkdir -p dev/playground-uploads
exec npx -y @wp-playground/cli@latest server \
	--port="$PORT" \
	--mount="$PWD/menudash:/wordpress/wp-content/plugins/menudash" \
	--mount="$PWD/dev/playground-uploads:/wordpress/wp-content/uploads" \
	--mount="$PWD/sample:/menudash-sample" \
	--mount="$PWD/dev:/menudash-dev" \
	--blueprint="$PWD/dev/blueprint.json" \
	--login
