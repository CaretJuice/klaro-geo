#!/bin/bash

# This script runs PHP tests directly without using the entrypoint script
# that requires a password for the www-data user

# Change to the docker directory
cd "$(dirname "$0")/docker"

# Clean up orphaned containers
docker compose down --remove-orphans 2>/dev/null || true

# First, set up the test environment as root
echo "Setting up test environment..."
docker compose run --user root --entrypoint bash wordpress_test -c "
  # Create test directories with proper permissions
  mkdir -p /tmp/wordpress-tests-lib
  mkdir -p /tmp/wordpress-tests-lib/includes
  mkdir -p /tmp/wordpress-tests-lib/data
  mkdir -p /var/www/html/wp-content/plugins/klaro-geo/vendor
  mkdir -p /var/www/html/wp-content/debug
  
  # Set permissions
  chown -R www-data:www-data /tmp/wordpress-tests-lib
  chown -R www-data:www-data /var/www/html/wp-content
  
  # Ensure WordPress test library exists
  if [ ! -f '/tmp/wordpress-tests-lib/includes/functions.php' ]; then
    svn co --quiet https://develop.svn.wordpress.org/tags/6.4.3/tests/phpunit/includes/ /tmp/wordpress-tests-lib/includes
    svn co --quiet https://develop.svn.wordpress.org/tags/6.4.3/tests/phpunit/data/ /tmp/wordpress-tests-lib/data
  fi
  
  # Create wp-tests-config.php if it doesn't exist
  if [ ! -f '/tmp/wordpress-tests-lib/wp-tests-config.php' ]; then
    cat > '/tmp/wordpress-tests-lib/wp-tests-config.php' <<EOF
<?php
define( 'DB_NAME', 'wordpress_test' );
define( 'DB_USER', 'wordpress' );
define( 'DB_PASSWORD', 'wordpress' );
define( 'DB_HOST', 'db_test' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'ABSPATH', '/var/www/html/' );
define( 'WP_TESTS_DIR', '/tmp/wordpress-tests-lib/' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', '/var/www/html/wp-content/debug.log' );
define( 'WP_DEBUG_DISPLAY', false );
EOF
  fi
  
  # Create and set permissions for debug log
  touch /var/www/html/wp-content/debug.log
  chmod 666 /var/www/html/wp-content/debug.log
  chown www-data:www-data /var/www/html/wp-content/debug.log
  
  # Ensure the tests directory is properly mounted
  mkdir -p /var/www/html/wp-content/plugins/klaro-geo/tests/phpunit
  cp -r /var/www/html/wp-content/plugins/klaro-geo/tests/phpunit/* /var/www/html/wp-content/plugins/klaro-geo/tests/phpunit/ 2>/dev/null || true
  
  echo 'Test environment set up successfully!'
"

# Run the PHP tests as www-data user
echo "Running PHP tests..."
docker compose run --user www-data --entrypoint bash wordpress_test -c "
  cd /var/www/html/wp-content/plugins/klaro-geo && 
  phpunit -c phpunit.xml
"

echo "PHP tests completed!"

# Display the test logs
if [ -f "$(dirname "$0")/docker/logs.sh" ]; then
  echo "Displaying test logs..."
  bash "$(dirname "$0")/docker/logs.sh" view
else
  echo "Test log viewer script not found"
fi