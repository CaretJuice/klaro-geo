#!/usr/bin/env bash
# Run WordPress.org's Plugin Check against the build output, as a reviewer would.
#
# Builds the release directory (./build-plugin.sh), copies it into the E2E site's
# container and runs `wp plugin check` on it. Fails on any error or warning.
# Starts the E2E site if it is not running; needs Docker and network access.
set -euo pipefail

cd "$(dirname "$0")/.."

container="klaro-geo-wordpress-e2e"
wp=(docker exec "$container" wp --allow-root --path=/var/www/html)

./build-plugin.sh >/dev/null

if ! docker ps --format '{{.Names}}' | grep -qx "$container"; then
	docker compose -f docker-compose.e2e.yml up -d
fi
bin/wait-for-e2e.sh

if ! "${wp[@]}" plugin is-installed plugin-check 2>/dev/null; then
	"${wp[@]}" plugin install plugin-check
fi
"${wp[@]}" plugin activate plugin-check >/dev/null 2>&1 || true

docker exec "$container" rm -rf /tmp/klaro-geo
docker cp build/klaro-geo "$container:/tmp/klaro-geo"

report="$(mktemp)"
trap 'rm -f "$report"' EXIT
"${wp[@]}" plugin check /tmp/klaro-geo --format=csv 2>/dev/null >"$report" || true

# CSV rows: line,column,type,code,message,docs. The first line of each file's
# block names the file.
findings="$(grep -cE '^[0-9]+,[0-9]+,(ERROR|WARNING),' "$report" || true)"
if [ "$findings" -gt 0 ]; then
	cat "$report"
	echo
	echo "Plugin Check reported $findings finding(s)." >&2
	exit 1
fi

echo "Plugin Check: no errors or warnings."
