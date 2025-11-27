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
chown -R www-data:www-data /var/www/html/wp-content

# Create and set permissions for debug log
touch /var/www/html/wp-content/debug.log
chmod 666 /var/www/html/wp-content/debug.log
chown www-data:www-data /var/www/html/wp-content/debug.log

# Also create a debug log in the root directory (some configurations write here)
touch /var/www/html/debug.log
chmod 666 /var/www/html/debug.log
chown www-data:www-data /var/www/html/debug.log

# Create logs directory in the plugin with proper permissions
mkdir -p /var/www/html/wp-content/plugins/klaro-geo/docker/logs
chmod -R 777 /var/www/html/wp-content/plugins/klaro-geo/docker/logs
chown -R www-data:www-data /var/www/html/wp-content/plugins/klaro-geo/docker/logs

# Ensure klaro-config.js is writable (tests need to write to this file)
touch /var/www/html/wp-content/plugins/klaro-geo/klaro-config.js
chmod 666 /var/www/html/wp-content/plugins/klaro-geo/klaro-config.js
chown www-data:www-data /var/www/html/wp-content/plugins/klaro-geo/klaro-config.js

# Print debug information
echo "Debug log permissions:"
ls -la /var/www/html/wp-content/debug.log
ls -la /var/www/html/debug.log
echo "Logs directory permissions:"
ls -la /var/www/html/wp-content/plugins/klaro-geo/docker/logs

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

echo "Copying debug logs after test execution..."

# Define the target log file
TARGET_LOG="/var/www/html/wp-content/plugins/klaro-geo/docker/logs/debug_test.log"

# Create a fresh log file with a header
echo "=== Klaro Geo Test Logs $(date) ===" > "$TARGET_LOG"
echo "" >> "$TARGET_LOG"

# Check if the WordPress debug log exists and copy it
WP_DEBUG_LOG="/var/www/html/wp-content/debug.log"
if [ -f "$WP_DEBUG_LOG" ]; then
    echo "Found WordPress debug log at $WP_DEBUG_LOG"
    echo "=== WordPress Debug Log ===" >> "$TARGET_LOG"
    cat "$WP_DEBUG_LOG" >> "$TARGET_LOG"
    echo "" >> "$TARGET_LOG"
    echo "WordPress debug log copied successfully."
else
    echo "WARNING: WordPress debug log not found at $WP_DEBUG_LOG"
    echo "=== WordPress Debug Log Not Found ===" >> "$TARGET_LOG"
    echo "The debug log file was not found at $WP_DEBUG_LOG" >> "$TARGET_LOG"
    echo "" >> "$TARGET_LOG"
fi

# Also check for a debug log in the WordPress root directory
ROOT_DEBUG_LOG="/var/www/html/debug.log"
if [ -f "$ROOT_DEBUG_LOG" ]; then
    echo "Found root debug log at $ROOT_DEBUG_LOG"
    echo "=== Root Debug Log ===" >> "$TARGET_LOG"
    cat "$ROOT_DEBUG_LOG" >> "$TARGET_LOG"
    echo "" >> "$TARGET_LOG"
    echo "Root debug log copied successfully."
fi

# Add system information
echo "=== System Information ===" >> "$TARGET_LOG"
echo "Date: $(date)" >> "$TARGET_LOG"
echo "PHP Version: $(php -v | head -n 1)" >> "$TARGET_LOG"
echo "" >> "$TARGET_LOG"

# Add file permissions information
echo "=== File Permissions ===" >> "$TARGET_LOG"
echo "Debug log permissions:" >> "$TARGET_LOG"
ls -la "$WP_DEBUG_LOG" 2>/dev/null >> "$TARGET_LOG"
ls -la "$ROOT_DEBUG_LOG" 2>/dev/null >> "$TARGET_LOG"
echo "" >> "$TARGET_LOG"

# Make sure the target log file has the right permissions
chmod 666 "$TARGET_LOG"
chown www-data:www-data "$TARGET_LOG"

echo "Log file saved to $TARGET_LOG"

# Verify the log file exists and has content
if [ -s "$TARGET_LOG" ]; then
    echo "Log file created successfully with $(wc -l < "$TARGET_LOG") lines."
else
    echo "WARNING: Log file is empty or was not created properly."
fi