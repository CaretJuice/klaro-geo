#!/bin/bash

# This script fixes npm permissions issues in the Docker container
# Run it with: ./docker/fix-npm-permissions.sh

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

echo "Fixing npm permissions in the Docker container..."
echo "Running from directory: $(pwd)"

# Run the fix in the Docker container with root privileges
docker compose run --user root --entrypoint bash wordpress_test -c "
  echo 'Running as user:' \$(id)

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

  # Set npm config to use the new cache directory
  npm config set cache /tmp/npm-cache

  echo 'npm permissions fixed successfully!'
"

echo "Done!"