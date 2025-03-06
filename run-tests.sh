#!/bin/bash

# Change to the docker directory
cd "$(dirname "$0")/docker"

# Clean up orphaned containers
docker compose down --remove-orphans 2>/dev/null || true

# Check for the first argument to determine which tests to run
if [ "$1" == "js" ]; then
  echo "Running JavaScript tests..."
  docker compose run wordpress_test bash -c "cd /var/www/html/wp-content/plugins/klaro-geo && npm install && npm test"
else
  echo "Running PHP tests..."
  # First, ensure the tests directory is properly mounted
  docker compose run wordpress_test bash -c "mkdir -p /var/www/html/wp-content/plugins/klaro-geo/tests/phpunit && cp -r /var/www/html/wp-content/plugins/klaro-geo/tests/phpunit/* /var/www/html/wp-content/plugins/klaro-geo/tests/phpunit/ 2>/dev/null || true"
  # Then run the tests
  docker compose run wordpress_test bash -c "cd /var/www/html/wp-content/plugins/klaro-geo && phpunit -c phpunit.xml"
fi