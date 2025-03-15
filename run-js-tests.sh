#!/bin/bash

# This script runs JavaScript tests directly without using the entrypoint script
# that requires a password for the www-data user

# Change to the docker directory
cd "$(dirname "$0")/docker"

# Clean up orphaned containers
docker compose down --remove-orphans 2>/dev/null || true

# First, fix permissions using root
echo "Setting up npm permissions..."
docker compose run --user root --entrypoint bash wordpress_test -c "
  # Create .npm directory with correct permissions if it doesn't exist
  mkdir -p /var/www/.npm

  # Fix ownership of npm cache directory
  chown -R www-data:www-data /var/www/.npm
  chmod -R 777 /var/www/.npm

  # Switch to the plugin directory
  cd /var/www/html/wp-content/plugins/klaro-geo

  # Create node_modules with correct permissions if it doesn't exist
  mkdir -p node_modules
  chown -R www-data:www-data node_modules
  chmod -R 777 node_modules

  # Create a .npmrc file to use a different cache directory
  echo 'cache=/tmp/npm-cache' > /var/www/html/wp-content/plugins/klaro-geo/.npmrc
  mkdir -p /tmp/npm-cache
  chown -R www-data:www-data /tmp/npm-cache
  chmod -R 777 /tmp/npm-cache

  echo 'npm permissions set up successfully!'
"

# Run tests as root to avoid permission issues
echo "Running JavaScript tests..."
if [ -n "$1" ]; then
  echo "Running specific test: $1..."
  docker compose run --user root --entrypoint bash wordpress_test -c "
    cd /var/www/html/wp-content/plugins/klaro-geo &&
    export HOME=/tmp &&
    npm config set cache /tmp/npm-cache &&
    npm install &&
    npm test -- -t \"$1\" | tee jest-output.log || echo 'Tests failed with status code: $?'
  "
else
  echo "Running all tests..."
  docker compose run --user root --entrypoint bash wordpress_test -c "
    cd /var/www/html/wp-content/plugins/klaro-geo &&
    export HOME=/tmp &&
    npm config set cache /tmp/npm-cache &&
    npm install &&
    npm test | tee jest-output.log || echo 'Tests failed with status code: $?'
  "
fi

echo "JavaScript tests completed!"

# Display the test logs
if [ -f "$(dirname "$0")/docker/view-test-logs.sh" ]; then
  echo "Displaying test logs..."
  bash "$(dirname "$0")/docker/view-test-logs.sh"
else
  echo "Test log viewer script not found"
fi