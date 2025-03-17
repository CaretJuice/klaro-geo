#!/bin/bash
# Make this script executable with: chmod +x docker/run-mock-tests.sh
# This script runs the tests with a mock database

# Change to the docker directory
cd "$(dirname "$0")"

# Make sure the container is running
if ! docker compose ps | grep -q wordpress_test; then
  echo "Starting WordPress test container..."
  docker compose up -d wordpress_test
  sleep 5
fi

# Run the tests with the mock database bootstrap
echo "Running tests with mock database..."
docker compose exec wordpress_test bash -c "cd /var/www/html/wp-content/plugins/klaro-geo && vendor/bin/phpunit --bootstrap tests/phpunit/bootstrap-mock.php tests/phpunit/ConsentReceiptsTest.php tests/phpunit/ConsentReceiptsAdminTest.php tests/phpunit/ConsentReceiptsIntegrationTest.php"

# Copy and view the logs
echo "Copying and viewing logs..."
if [ -f "$(dirname "$0")/logs.sh" ]; then
  bash "$(dirname "$0")/logs.sh" both
else
  # Fallback if logs.sh is not found
  docker compose exec wordpress_test bash -c "cat /var/www/html/wp-content/debug.log" > logs/debug_test.log
  echo "Logs copied to logs/debug_test.log"
fi

echo "Tests completed."