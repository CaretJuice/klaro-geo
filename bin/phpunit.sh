#!/usr/bin/env bash
# Run the PHP test suite in Docker. Arguments go to PHPUnit:
#
#   bin/phpunit.sh
#   bin/phpunit.sh --filter KlaroGeoConsentModeTest
#
# On another WordPress or PHP (each pair is built once and kept):
#
#   WP_VERSION=6.6.12 PHP_VERSION=7.4 bin/phpunit.sh
#
# PHPUnit, WordPress and its test library are in the image. The plugin's debug
# log of the run is docker/logs/debug_test.log.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"

# Without a terminal (CI, a pipe) docker must not be asked for one.
tty=()
[ -t 1 ] || tty=(-T)

HOST_UID="$(id -u)" HOST_GID="$(id -g)" \
	docker compose -f "$root/docker/docker-compose.yml" --profile test \
	run --rm --build --quiet-pull "${tty[@]}" phpunit phpunit "$@"
