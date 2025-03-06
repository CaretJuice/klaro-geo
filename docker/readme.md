# Docker Development Environment for Klaro Geo

This directory contains all Docker-related files for the Klaro Geo plugin development environment. These tools are primarily intended for plugin developers and contributors.

## Contents

- `docker-compose.yml` - Main Docker Compose configuration
- `docker-entrypoint-test.sh` - Entrypoint script for the test container
- `php.ini` and `custom.ini` - PHP configuration for the containers
- `test-logging.sh` - Script to test logging functionality
- `copy-debug-log.sh` - Script to copy debug logs from the container
- `debug-database.sh` - Script to debug database issues
- `run-mock-tests.sh` - Script to run tests with mock database
- `logs/` - Directory for storing debug logs

## Getting Started

For basic setup instructions, see the [main README.md](../readme.md) and [readme-dev.md](../readme-dev.md) files in the plugin root directory.

## Running Tests

Klaro Geo includes comprehensive test suites for both PHP and JavaScript code.

### Standard PHP Tests

To run the standard PHP tests:

```bash
cd docker
docker compose run wordpress_test
```

Or use the convenience script from the plugin root:

```bash
chmod +x run-tests.sh
./run-tests.sh
```

### JavaScript Tests

To run the JavaScript tests:

```bash
chmod +x run-tests.sh
./run-tests.sh js
```

### Tests with Mock Database

If you're having issues with database permissions or configuration, you can use the mock database implementation:

```bash
chmod +x docker/run-mock-tests.sh
./docker/run-mock-tests.sh
```

This will run the tests with a mock database implementation that doesn't require actual database tables.

## Debugging

### Debug Log Configuration

In the WordPress test environment, debug logs are configured in two places:

1. **wp-tests-config.php** - Contains the following settings:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', '/var/www/html/wp-content/debug.log');
   define('WP_DEBUG_DISPLAY', false);
   ```

2. **bootstrap.php** - Contains additional PHP settings:
   ```php
   ini_set('error_reporting', E_ALL);
   ini_set('display_errors', '1');
   ini_set('display_startup_errors', '1');
   ini_set('log_errors', '1');
   ini_set('error_log', '/var/www/html/wp-content/debug.log');
   ```

### Logging Functions

Klaro Geo provides several ways to write to the debug log:

1. **Custom klaro_geo_debug_log function** (recommended):
   ```php
   klaro_geo_debug_log('This is a message from klaro_geo_debug_log()');
   ```

2. **PHP's error_log function**:
   ```php
   error_log('This is a message from error_log()');
   ```

3. **WordPress's wp_debug_log function** (if available):
   ```php
   if (function_exists('wp_debug_log')) {
       wp_debug_log('This is a message from wp_debug_log()');
   }
   ```

### Viewing Debug Logs

You can view the debug logs in several ways:

1. **Using the copy-debug-log.sh script**:
   ```bash
   chmod +x copy-debug-log.sh
   ./copy-debug-log.sh
   ```
   This copies the log to `logs/debug_test.log` in your host machine.

2. **Directly from the container**:
   ```bash
   docker exec wordpress_test cat /var/www/html/wp-content/debug.log
   ```

3. **After tests complete**: The log is automatically copied to the `logs` directory.

### Testing Logging Functionality

To verify that logging is working correctly:

```bash
chmod +x docker/test-logging.sh
./docker/test-logging.sh
```

This script tests various logging methods and displays the results.

### Troubleshooting Logging Issues

If logging is not working:

1. **Check file permissions**:
   ```bash
   docker compose exec wordpress_test ls -la /var/www/html/wp-content/debug.log
   ```

2. **Check directory permissions**:
   ```bash
   docker compose exec wordpress_test ls -la /var/www/html/wp-content/
   ```

3. **Check PHP configuration**:
   ```bash
   docker compose exec wordpress_test php -i | grep error_log
   ```

4. **Check WordPress configuration**:
   ```bash
   docker compose exec wordpress_test cat /tmp/wordpress-tests-lib/wp-tests-config.php | grep WP_DEBUG
   ```

## Mock Database for Tests

The mock database implementation is useful when you want to run tests without a real database connection.

### How It Works

The mock database implementation:

1. Overrides the database functions used by the plugin
2. Stores data in memory instead of in a database
3. Simulates database operations like CREATE TABLE, INSERT, SELECT, etc.

### Files

- `tests/phpunit/mock-db.php`: The main mock database implementation
- `tests/phpunit/bootstrap-mock.php`: A bootstrap file that sets up the mock database
- `tests/phpunit/test-helpers.php`: Helper functions for tests

### Debugging Database Issues

If you're having issues with the database, use the debug script:

```bash
chmod +x docker/debug-database.sh
./docker/debug-database.sh
```

This script tests database connectivity and displays configuration information.

## Additional Resources

- For more information about the plugin's features, see the [main README.md](../readme.md)
- For development setup instructions, see [readme-dev.md](../readme-dev.md)
- For JavaScript testing details, see the test files in `tests/js/`