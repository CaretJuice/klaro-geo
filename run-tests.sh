#!/bin/bash

# Get the script's directory (the plugin root)
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# Fix permissions on host before running Docker
# These files need to be writable by www-data (UID 33) inside the container
echo "Setting up file permissions on host..."
touch "$SCRIPT_DIR/klaro-config.js"
touch "$SCRIPT_DIR/.phpunit.result.cache"
chmod 666 "$SCRIPT_DIR/klaro-config.js"
chmod 666 "$SCRIPT_DIR/.phpunit.result.cache"

# Change to the docker directory
cd "$SCRIPT_DIR/docker"

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
  docker compose run wordpress_test bash -c "cd /var/www/html/wp-content/plugins/klaro-geo && phpunit -c phpunit.xml"
fi