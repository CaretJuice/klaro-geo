#!/bin/bash
set -e  # Exit on error

# Create test directories with proper permissions
dirs=(
    "/tmp/wordpress-tests-lib"
    "/tmp/wordpress-tests-lib/includes"
    "/tmp/wordpress-tests-lib/data"
    "/var/www/html/wp-content/plugins/klaro-geo/vendor"
    "/var/www/html/wp-content/debug"
)

for dir in "${dirs[@]}"; do
    mkdir -p "$dir"
    chown www-data:www-data "$dir"
    chmod 755 "$dir"
done


# Copy polyfills to plugin directory if they don't exist
if [ ! -d "/var/www/html/wp-content/plugins/klaro-geo/vendor" ]; then
    mkdir -p /var/www/html/wp-content/plugins/klaro-geo/vendor 2>/dev/null || true
    cp -r /tmp/polyfills/vendor/* /var/www/html/wp-content/plugins/klaro-geo/vendor/ 2>/dev/null || true
    chown -R www-data:www-data /var/www/html/wp-content/plugins/klaro-geo/vendor
fi

# Ensure WordPress test library exists and has correct permissions
if [ ! -f "/tmp/wordpress-tests-lib/includes/functions.php" ]; then
    mkdir -p /tmp/wordpress-tests-lib
    svn co --quiet https://develop.svn.wordpress.org/tags/6.4.3/tests/phpunit/includes/ /tmp/wordpress-tests-lib/includes
    svn co --quiet https://develop.svn.wordpress.org/tags/6.4.3/tests/phpunit/data/ /tmp/wordpress-tests-lib/data
fi
chown -R www-data:www-data /tmp/wordpress-tests-lib

# Function to wait for MySQL to be ready
wait_for_mysql() {
    until mysql -h"$WORDPRESS_DB_HOST" -u"$WORDPRESS_DB_USER" -p"$WORDPRESS_DB_PASSWORD" -e "SELECT 1" >/dev/null 2>&1; do
        echo "Waiting for MySQL to be ready..."
        sleep 2
    done
    echo "MySQL is up - executing command"
}

# Wait for MySQL
wait_for_mysql

# Function to reset the database
reset_database() {
  mysql -h"$WORDPRESS_DB_HOST" -u"$WORDPRESS_DB_USER" -p"$WORDPRESS_DB_PASSWORD" -e "DROP DATABASE IF EXISTS \`$WORDPRESS_DB_NAME\`; CREATE DATABASE \`$WORDPRESS_DB_NAME\`;"
}

# Reset the database before running tests
reset_database

# Create wp-tests-config.php if it doesn't exist
if [ ! -f "/tmp/wordpress-tests-lib/wp-tests-config.php" ]; then
    echo "Creating wp-tests-config.php in /tmp/wordpress-tests-lib/"
    cat > "/tmp/wordpress-tests-lib/wp-tests-config.php" <<EOF
<?php
define( 'DB_NAME', '${WORDPRESS_DB_NAME}' );
define( 'DB_USER', '${WORDPRESS_DB_USER}' );
define( 'DB_PASSWORD', '${WORDPRESS_DB_PASSWORD}' );
define( 'DB_HOST', '${WORDPRESS_DB_HOST}' );
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
    chown www-data:www-data "/tmp/wordpress-tests-lib/wp-tests-config.php"
fi

# Make sure wp-content directory is writable
mkdir -p /var/www/html/wp-content
touch /var/www/html/wp-content/debug.log
chmod 666 /var/www/html/wp-content/debug.log
chown www-data:www-data /var/www/html/wp-content/debug.log

# Install geoip-detect plugin if it doesn't exist
if [ ! -d /var/www/html/wp-content/plugins/geoip-detect ]; then
  echo "Installing geoip-detect plugin..."
  mkdir -p /var/www/html/wp-content/plugins
  cd /var/www/html/wp-content/plugins
  curl -L https://downloads.wordpress.org/plugin/geoip-detect.latest-stable.zip -o geoip-detect.zip
  unzip geoip-detect.zip
  rm geoip-detect.zip
  chown -R www-data:www-data /var/www/html/wp-content/plugins/geoip-detect
  echo "geoip-detect plugin installed successfully"
fi

echo "Configuration complete"

ls -l /var/www/html/wp-content

# Execute the command as www-data
cd /var/www/html/wp-content/plugins/klaro-geo

echo "Checking directory contents and permissions:"
ls -la /var/www/html/wp-content/plugins/klaro-geo

# Install Composer dependencies if they don't exist
if [ ! -f "/var/www/html/wp-content/plugins/klaro-geo/vendor/autoload.php" ]; then
  echo "Installing Composer dependencies..."
  cd /var/www/html/wp-content/plugins/klaro-geo
  composer install --no-interaction
  # Fix permissions on vendor directory
  chown -R www-data:www-data /var/www/html/wp-content/plugins/klaro-geo/vendor
  chmod -R 755 /var/www/html/wp-content/plugins/klaro-geo/vendor
  echo "Composer dependencies installed successfully"
fi

# Verify autoload.php exists
if [ -f "/var/www/html/wp-content/plugins/klaro-geo/vendor/autoload.php" ]; then
  echo "autoload.php exists"
else
  echo "ERROR: autoload.php still missing after composer install"
  ls -la /var/www/html/wp-content/plugins/klaro-geo/vendor
fi

# Store the command to execute
test_command="$@"

if [[ "$test_command" == "sleep 9999999" ]]; then
  # Run sleep command directly without su
  sleep 9999999
else
  # Run other commands with su
  su -s /bin/bash www-data -c "export PATH=$PATH:/usr/local/bin && $test_command"
fi

echo "List wp-content before copying debug.log:"
ls -la /var/www/html/wp-content
# Copy debug.log after tests finish
if [ -f /var/www/html/wp-content/debug.log ]; then
  echo "Debug log found, copying to logs directory..."
  mkdir -p /var/www/html/wp-content/plugins/klaro-geo/docker/logs/
  chmod 777 /var/www/html/wp-content
  cp /var/www/html/wp-content/debug.log /var/www/html/wp-content/plugins/klaro-geo/docker/logs/debug_test.log
  echo "Debug log copied to /var/www/html/wp-content/plugins/klaro-geo/docker/logs/debug_test.log"
  # Note: The docker cp command won't work from inside the container
  # It will be handled by the host when the container exits
else
  echo "No debug.log file found at /var/www/html/wp-content/debug.log"
  # Create an empty log file if it doesn't exist
  echo "Creating empty debug log file"
  echo "Debug log was empty" > /var/www/html/wp-content/plugins/klaro-geo/docker/logs/debug_test.log
fi