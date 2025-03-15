#!/bin/bash

# Change to the docker directory
cd "$(dirname "$0")/docker"

# Clean up orphaned containers
docker compose down --remove-orphans 2>/dev/null || true

# Check for the first argument to determine which tests to run
if [ "$1" == "js" ]; then
  # First, fix permissions using root
  echo "Setting up npm permissions..."
  docker compose run --user root wordpress_test bash -c "
    # Create .npm directory with correct permissions if it doesn't exist
    mkdir -p /var/www/.npm
    # Fix ownership of npm cache directory
    chown -R www-data:www-data /var/www/.npm
    # Switch to the plugin directory
    cd /var/www/html/wp-content/plugins/klaro-geo
    # Create node_modules with correct permissions if it doesn't exist
    mkdir -p node_modules
    chown -R www-data:www-data node_modules
    echo 'npm permissions set up successfully!'
  "

  # Check if a specific test file was specified
  if [ -n "$2" ]; then
    echo "Running JavaScript test file: $2..."
    # Run tests as www-data user
    docker compose run --user www-data wordpress_test bash -c "
      cd /var/www/html/wp-content/plugins/klaro-geo &&
      npm install &&
      npm test -- -t \"$2\"
    "
  else
    echo "Running all JavaScript tests..."
    # Run tests as www-data user
    docker compose run --user www-data wordpress_test bash -c "
      cd /var/www/html/wp-content/plugins/klaro-geo &&
      npm install &&
      npm test
    "
  fi
else
  echo "Running PHP tests..."
  # First, ensure the tests directory is properly mounted
  docker compose run wordpress_test bash -c "mkdir -p /var/www/html/wp-content/plugins/klaro-geo/tests/phpunit && cp -r /var/www/html/wp-content/plugins/klaro-geo/tests/phpunit/* /var/www/html/wp-content/plugins/klaro-geo/tests/phpunit/ 2>/dev/null || true"
  # Then run the tests
  docker compose run wordpress_test bash -c "cd /var/www/html/wp-content/plugins/klaro-geo && phpunit -c phpunit.xml"
fi