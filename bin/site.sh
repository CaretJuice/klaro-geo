#!/usr/bin/env bash
# The WordPress site used by the Playwright tests and for trying things by
# hand: http://localhost:8080, admin / password.
#
#   bin/site.sh up        start it; install WordPress if it is not installed
#   bin/site.sh reset     start it if needed and return it to its known state
#                         (empty database, fresh install, plugins active)
#   bin/site.sh down      stop it and delete its data
#   bin/site.sh wp ...    run WP-CLI in it, e.g. bin/site.sh wp option get klaro_geo_services
#   bin/site.sh log       follow the WordPress debug log
#
# The checkout is mounted read-only as the plugin, so edits are live.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
container="klaro-geo-site"

compose() {
	docker compose -f "$root/docker/docker-compose.yml" --profile site --profile test "$@"
}

# Start the containers and wait until WordPress's files and wp-config.php are
# in place (the site's healthcheck).
start() {
	compose up -d --build --quiet-pull --wait db site
}

case "${1:-}" in
	up)
		start
		docker exec "$container" site-setup
		;;
	reset)
		start
		docker exec "$container" site-setup reset
		;;
	down)
		compose down --volumes --remove-orphans
		;;
	wp)
		shift
		stdin=()
		[ -t 0 ] || stdin=(-i)
		docker exec "${stdin[@]}" "$container" wp "$@"
		;;
	log)
		docker exec "$container" sh -c 'touch wp-content/debug.log && tail -n 100 -f wp-content/debug.log'
		;;
	*)
		sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'
		exit 1
		;;
esac
