#!/bin/bash
# Make this script executable with: chmod +x run-country-test.sh
# Script to run the countryConfigTest.php with proper logging

# Set up environment
echo "Setting up environment for country config test..."

# Ensure debug log exists and is writable
DEBUG_LOG="/var/www/html/wp-content/debug.log"
touch "$DEBUG_LOG"
chmod 666 "$DEBUG_LOG"
echo "=== Starting Country Config Test $(date) ===" > "$DEBUG_LOG"

# Run the test with output capturing
echo "Running country config test..."
TEST_OUTPUT=$(cd /var/www/html/wp-content/plugins/klaro-geo && php -d error_reporting=E_ALL -d display_errors=0 -d log_errors=1 -d error_log="$DEBUG_LOG" vendor/bin/phpunit tests/phpunit/countryConfigTest.php 2>&1)

# Save the test output
echo "$TEST_OUTPUT" > /var/www/html/wp-content/plugins/klaro-geo/docker/logs/country_test_output.log
echo "Test output saved to docker/logs/country_test_output.log"

# Display the test output
echo "=== Test Output ==="
echo "$TEST_OUTPUT"
echo "=================="

# Copy the debug log
echo "Copying debug log..."
cp "$DEBUG_LOG" /var/www/html/wp-content/plugins/klaro-geo/docker/logs/country_test_debug.log
echo "Debug log copied to docker/logs/country_test_debug.log"

# Check if the test passed
if echo "$TEST_OUTPUT" | grep -q "OK ("; then
  echo "Test PASSED!"
  exit 0
else
  echo "Test FAILED!"
  exit 1
fi