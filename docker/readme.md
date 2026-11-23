# Docker Development Environment for Klaro Geo

This directory contains all Docker-related files for the Klaro Geo plugin development environment. These tools are primarily intended for plugin developers and contributors.

## Contents

- `docker-compose.yml` - the PHPUnit runner (`wordpress_test`, `db_test`) and the dev site (`wordpress`, `db`)
- `Dockerfile.test`, `docker-entrypoint-test.sh` - the PHPUnit runner image and its entrypoint
- `docker-entrypoint-wordpress.sh` - the dev site's entrypoint
- `php.ini` and `custom.ini` - PHP configuration for the containers
- `logs/` - debug logs copied out of the containers

## Running tests

```bash
cd docker
docker compose run --rm wordpress_test vendor/bin/phpunit
```

Add `--filter ClassName` for one test class. JavaScript tests run on the host with `npx jest` from the repository root. See [readme-dev.md](../readme-dev.md) for the rest.

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

1. **After tests complete**: the log is copied to `logs/debug_test.log` on your machine.

2. **Directly from the container**:
   ```bash
   docker exec wordpress_test cat /var/www/html/wp-content/debug.log
   ```

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

## Database

PHPUnit tests use the real test database provided by the `db_test` service. Tests that need the consent receipts table create it once per test class with `klaro_geo_create_consent_receipts_table()`.

## Additional Resources

- For more information about the plugin's features, see the [main README.md](../readme.md)
- For development setup instructions, see [readme-dev.md](../readme-dev.md)
- For JavaScript testing details, see the test files in `tests/js/`