#!/bin/bash
# This script tests the logging functionality in the WordPress test container

# Change to the docker directory
cd "$(dirname "$0")"

# Make sure the container is running
if ! docker compose ps | grep -q wordpress_test; then
  echo "Starting WordPress test container..."
  docker compose up -d wordpress_test
  sleep 5
fi

# Create a simple PHP script to test logging
cat > test-log.php <<EOF
<?php
// Test script for WordPress logging
require_once '/var/www/html/wp-content/plugins/klaro-geo/klaro.php';

// Make sure debug.log exists and is writable
\$debug_log = '/var/www/html/wp-content/debug.log';
if (!file_exists(\$debug_log)) {
    touch(\$debug_log);
    chmod(\$debug_log, 0666);
}

// Test standard PHP error_log
error_log('Standard PHP error_log test from test-log.php');

// Test our custom logging function
klaro_geo_debug_log('Custom klaro_geo_debug_log test from test-log.php');

// Write directly to the file
file_put_contents(\$debug_log, date('[Y-m-d H:i:s]') . ' [Direct Write] Test from test-log.php' . "\n", FILE_APPEND);

echo "Logging tests completed. Check debug.log for results.";
EOF

# Copy the test script to the container
docker compose exec wordpress_test mkdir -p /tmp/test
docker compose cp test-log.php wordpress_test:/tmp/test/

# Run the test script
echo "Running logging test script..."
docker compose exec wordpress_test php /tmp/test/test-log.php

# Display the debug log
echo -e "\nContents of debug.log:"
docker compose exec wordpress_test cat /var/www/html/wp-content/debug.log

# Clean up
rm test-log.php
echo -e "\nTest completed."