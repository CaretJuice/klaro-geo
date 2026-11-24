#!/usr/bin/env bash
# Wait until the E2E site (docker-compose.e2e.yml) has finished installing.
#
# WordPress answers on port 8080 before the entrypoint has installed it and
# activated the plugins, so waiting for the port is not enough: the entrypoint
# prints "E2E Environment Ready!" when it is done.
set -euo pipefail

container="klaro-geo-wordpress-e2e"
timeout_seconds="${1:-240}"

for ((waited = 0; waited < timeout_seconds; waited += 3)); do
	# Read the whole log before searching it. `docker logs | grep -q` can make
	# docker fail with SIGPIPE when grep stops at the first match, and with
	# pipefail that reads as "not ready" once the log is long.
	logs="$(docker logs "$container" 2>&1 || true)"
	if grep -q "E2E Environment Ready" <<<"$logs"; then
		exit 0
	fi
	sleep 3
done

echo "The E2E site was not ready after ${timeout_seconds}s. Last log lines:" >&2
docker logs --tail 20 "$container" >&2 || true
exit 1
