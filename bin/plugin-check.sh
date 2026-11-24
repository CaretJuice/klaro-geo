#!/usr/bin/env bash
# Run WordPress.org's Plugin Check against the build output, as a reviewer would.
#
# Builds the release directory (./build-plugin.sh), copies it into the site's
# container and runs `wp plugin check` on it. Fails on any error or warning.
# Starts the site if it is not running. The Plugin Check plugin is part of the
# image (docker/Dockerfile); it is active only while this script runs.
set -euo pipefail

cd "$(dirname "$0")/.."

container="klaro-geo-site"
wp=(docker exec "$container" wp)

./build-plugin.sh >/dev/null

if ! docker ps --format '{{.Names}}' | grep -qx "$container"; then
	bin/site.sh up
fi

docker exec "$container" rm -rf /tmp/klaro-geo
docker cp build/klaro-geo "$container:/tmp/klaro-geo"

report="$(mktemp)"
trap 'rm -f "$report"; "${wp[@]}" plugin deactivate plugin-check >/dev/null 2>&1 || true' EXIT

"${wp[@]}" plugin activate plugin-check >/dev/null
"${wp[@]}" plugin check /tmp/klaro-geo --format=csv 2>/dev/null >"$report" || true

# A run that could not check anything must not pass as "no findings".
if ! grep -q . "$report" && ! "${wp[@]}" plugin check /tmp/klaro-geo --format=csv >/dev/null; then
	echo "Plugin Check did not run." >&2
	exit 1
fi

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
