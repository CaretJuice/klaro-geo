#!/usr/bin/env bash
# Run the PHP test suite in Docker. Arguments go to PHPUnit:
#
#   bin/phpunit.sh
#   bin/phpunit.sh --filter KlaroGeoConsentModeTest
#
# PHPUnit itself comes from vendor/ (run `composer install` once); WordPress
# and its test library come from the image. The plugin's debug log of the run
# is docker/logs/debug_test.log.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"

if [ ! -x "$root/vendor/bin/phpunit" ]; then
	echo "vendor/bin/phpunit is missing. Run 'composer install' in $root first." >&2
	exit 1
fi

# Without a terminal (CI, a pipe) docker must not be asked for one.
tty=()
[ -t 1 ] || tty=(-T)

HOST_UID="$(id -u)" HOST_GID="$(id -g)" \
	docker compose -f "$root/docker/docker-compose.yml" --profile test \
	run --rm --build --quiet-pull "${tty[@]}" phpunit vendor/bin/phpunit "$@"
