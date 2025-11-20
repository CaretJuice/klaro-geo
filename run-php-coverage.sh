#!/bin/bash

# Script to run PHP tests with coverage

cd "$(dirname "$0")/docker"

# Clean up orphaned containers
docker compose down --remove-orphans 2>/dev/null || true

# Set up test environment as root
echo "Setting up test environment with coverage..."
docker compose run --user root --entrypoint bash wordpress_test -c "
  # Create test directories
  mkdir -p /tmp/wordpress-tests-lib
  mkdir -p /tmp/wordpress-tests-lib/includes
  mkdir -p /tmp/wordpress-tests-lib/data
  mkdir -p /var/www/html/wp-content/plugins/klaro-geo/vendor
  mkdir -p /var/www/html/wp-content/plugins/klaro-geo/coverage/html
  
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
    cat > '/tmp/wordpress-tests-lib/wp-tests-config.php' <<EOFCONFIG
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
EOFCONFIG
  fi
  
  # Create debug log
  touch /var/www/html/wp-content/debug.log
  chmod 666 /var/www/html/wp-content/debug.log
  chown www-data:www-data /var/www/html/wp-content/debug.log
  
  echo 'Test environment set up successfully!'
"

# Run PHP tests with coverage
echo "Running PHP tests with coverage..."
docker compose run --user www-data --entrypoint bash wordpress_test -c "
  cd /var/www/html/wp-content/plugins/klaro-geo && 
  XDEBUG_MODE=coverage phpunit -c phpunit.xml --coverage-text --coverage-html coverage/html
"

echo "PHP coverage tests completed!"
echo "HTML coverage report generated in coverage/html/"
