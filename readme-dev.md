# Development Setup for Klaro Geo

This document provides instructions for setting up a development environment and running tests for the Klaro Geo plugin. The project uses Docker for development, making it easy to get started without configuring a local WordPress installation.

## Prerequisites

- Docker
- Docker Compose
- Git
- Node.js and npm (for JavaScript development)

## Project Structure

- `/docker` - Contains all Docker-related files and configuration
- `/includes` - PHP classes and core functionality
- `/js` - JavaScript source files
- `/css` - CSS stylesheets
- `/tests` - Test files
  - `/phpunit` - PHP unit tests
  - `/js` - JavaScript tests
- `/vendor` - Composer dependencies (generated)

## Key Files

- `klaro-geo.php` - Main plugin file
- `klaro-config.js` - Generated Klaro configuration
- `readme.md` - User documentation
- `readme-dev.md` - Developer documentation (this file)
- `phpunit.xml` - PHPUnit configuration
- `package.json` - npm configuration for JavaScript development

## Setting Up the Development Environment

### On Linux/macOS:
```bash
# Clone the repository (if you haven't already)
git clone https://github.com/your-repo/klaro-geo.git
cd klaro-geo

# Make the startup script executable
chmod +x start-dev-environment.sh

# Start the development environment
./start-dev-environment.sh
```

### On Windows:
```powershell
# Clone the repository (if you haven't already)
git clone https://github.com/your-repo/klaro-geo.git
cd klaro-geo

# Start the development environment
.\start-dev-environment.ps1
```

The development environment will be available at:
- WordPress: http://localhost:8000
- Admin: http://localhost:8000/wp-admin (username: `admin`, password: `password`)

No manual configuration is required - the scripts handle everything automatically, including:
- Setting up WordPress
- Installing and activating the plugin
- Configuring the GeoIP Detection plugin
- Creating test data

## Development Workflow

1. Make changes to the plugin files
2. Test your changes in the development environment
3. Write and run tests to verify functionality
4. Commit your changes

## Running Tests

The test suite is comprehensive and includes both PHP and JavaScript tests.

### Quick Start for Running Tests

To make all scripts executable and run tests without password prompts:

```bash
# Make all scripts executable
bash fix-permissions.sh

# Run PHP tests (no password required)
./run-php-tests.sh

# Run JavaScript tests (no password required)
./run-js-tests.sh

# Run a specific JavaScript test (no password required)
./run-js-tests.sh "test-name"
```

### Available Test Scripts

#### No-Password Scripts (Recommended)

These scripts bypass the Docker entrypoint script that requires a password:

- `run-php-tests.sh` - Runs all PHP tests
- `run-js-tests.sh` - Runs all JavaScript tests
- `run-js-tests.sh "test-name"` - Runs a specific JavaScript test

#### Direct Test Script (Most Reliable)

If you're having issues with npm permissions, use the direct test script:

- `run-js-tests-direct.sh` - Runs JavaScript tests in a fresh container without using the existing npm cache
- `run-js-tests-direct.sh "test-name"` - Runs a specific JavaScript test in a fresh container

#### Original Scripts (May Require Password)

The original test scripts may prompt for a password:

- `run-tests.sh` - Runs all PHP tests
- `run-tests.sh js` - Runs all JavaScript tests
- `run-tests.sh js "test-name"` - Runs a specific JavaScript test

### Alternative Methods for Running Tests

#### PHP Tests

##### Method 1: Using the run-tests.sh Script

```bash
# Make the script executable
chmod +x run-tests.sh

# Run all PHP tests
./run-tests.sh

# Run tests with verbose output
./run-tests.sh -v
```

##### Method 2: From Your Host Machine

```bash
# Change to the docker directory
cd docker

# Run all tests
docker compose run wordpress_test bash -c "cd /var/www/html/wp-content/plugins/klaro-geo && phpunit -c phpunit.xml"

# Run a specific test file
docker compose run wordpress_test bash -c "cd /var/www/html/wp-content/plugins/klaro-geo && phpunit -c phpunit.xml tests/phpunit/klaroGeoRegionsTest.php"
```

##### Method 3: Inside the Test Container

```bash
# Change to the docker directory
cd docker

# Enter the container
docker compose run wordpress_test bash

# Change to the plugin directory
cd /var/www/html/wp-content/plugins/klaro-geo

# Run all tests
phpunit -c phpunit.xml
```

#### JavaScript Tests

The JavaScript tests use Jest and are located in the `/tests/js/` directory.

##### Method 1: Using the run-tests.sh Script

```bash
# Run all JavaScript tests
./run-tests.sh js

# Run a specific JavaScript test file
./run-tests.sh js test-consent-receipts.js
```

##### Method 2: Manually Using npm

```bash
# Enter the container
docker compose run wordpress_test bash

# Navigate to the plugin directory
cd /var/www/html/wp-content/plugins/klaro-geo

# Install dependencies
npm install

# Run all JavaScript tests
npm test

# Run tests in watch mode (for development)
npm test -- --watch
```

## Fixing Permissions

If you encounter permission issues, use the fix-permissions.sh script:

```bash
bash fix-permissions.sh
```

This will make all scripts executable.

## Fixing npm Permissions

If you encounter npm-specific permission issues:

```bash
./docker/fix-npm-permissions.sh
```

Or run it with bash if it's not executable:

```bash
bash docker/fix-npm-permissions.sh
```

## Viewing Test Logs

After running tests, you can view the logs with:

```bash
./docker/logs.sh view    # View logs only
./docker/logs.sh copy    # Copy logs from container to host
./docker/logs.sh both    # Copy and then view logs (default)
```

This will display:
- WordPress debug logs
- npm logs
- Jest test output

The logs are also saved to the `docker/logs` directory.

## Debugging

### Debug Logs

Debug logs are stored in the `/docker/logs` directory. The logs are mounted into the Docker containers, so you can view them from your host machine.

To enable detailed logging during development:

1. Add debug statements in your code:
   ```php
   klaro_geo_debug_log('Detailed information about what is happening');
   ```

2. View the logs:
   ```bash
   # Copy logs from the container to your host and view them
   ./docker/logs.sh both

   # Or just copy the logs
   ./docker/logs.sh copy

   # Or just view the logs (if already copied)
   ./docker/logs.sh view
   ```

### Mock Database

For testing without a real database:

```bash
# Run tests with the mock database
./docker/run-mock-tests.sh
```

## Troubleshooting

### Password Prompts

If you see a password prompt when running tests, use the no-password scripts instead:

```bash
# Instead of: ./run-tests.sh js
./run-js-tests.sh
```

### npm Permission Errors

If you see errors like:

```
npm ERR! code EACCES
npm ERR! syscall mkdir
npm ERR! path /var/www/.npm
```

Try one of these solutions:

1. Run the npm permissions fix script:
   ```bash
   bash docker/fix-npm-permissions.sh
   ```

2. Use the direct test script which avoids npm permission issues:
   ```bash
   bash run-js-tests-direct.sh
   ```

### No Test Output

If tests run but you don't see any output, check the logs:

```bash
./docker/view-test-logs.sh
```

### Script Not Executable

If you see "Permission denied" errors when running scripts:

```bash
bash fix-permissions.sh
```

Or run the script directly with bash:

```bash
bash run-js-tests.sh
```

## Building for Production

When you're ready to create a production version of the plugin:

1. Run the JavaScript tests to ensure everything works
2. Ensure all PHP tests pass
3. Update version numbers in:
   - `klaro-geo.php` (Plugin header)
   - `readme.md` (if applicable)
4. Create a zip file of the plugin directory, excluding development files:
   ```bash
   # Example (adjust as needed)
   zip -r klaro-geo.zip . -x "*.git*" -x "docker/*" -x "tests/*" -x "node_modules/*"
   ```

## Additional Resources

- For more detailed information about Docker setup, see [docker/readme.md](docker/readme.md)
- For user documentation, see [readme.md](readme.md)
- For JavaScript testing details, see the test files in `tests/js/`