# Development Setup for Klaro Geo

This document provides instructions for setting up a development environment for the Klaro Geo plugin. The project uses Docker for development, making it easy to get started without configuring a local WordPress installation.

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

- `klaro.php` - Main plugin file
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

### PHP Tests

#### Method 1: Using the run-tests.sh Script (Recommended)

```bash
# Make the script executable
chmod +x run-tests.sh

# Run all PHP tests
./run-tests.sh

# Run tests with verbose output
./run-tests.sh -v
```

#### Method 2: From Your Host Machine

```bash
# Change to the docker directory
cd docker

# Run all tests
docker compose run wordpress_test bash -c "cd /var/www/html/wp-content/plugins/klaro-geo && phpunit -c phpunit.xml"

# Run a specific test file
docker compose run wordpress_test bash -c "cd /var/www/html/wp-content/plugins/klaro-geo && phpunit -c phpunit.xml tests/phpunit/klaroGeoRegionsTest.php"
```

#### Method 3: Inside the Test Container

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

### JavaScript Tests

The JavaScript tests use Jest and are located in the `/tests/js/` directory.

#### Method 1: Using the run-tests.sh Script (Recommended)

```bash
# Run all JavaScript tests
./run-tests.sh js

# Run a specific JavaScript test file
./run-tests.sh js test-consent-receipts.js
```

#### Method 2: Manually Using npm

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
   # Copy logs from the container to your host
   ./docker/copy-debug-log.sh

   # View the logs
   cat docker/logs/debug_test.log
   ```

### Mock Database

For testing without a real database:

```bash
# Run tests with the mock database
./docker/run-mock-tests.sh
```

## Building for Production

When you're ready to create a production version of the plugin:

1. Run the JavaScript tests to ensure everything works
2. Ensure all PHP tests pass
3. Update version numbers in:
   - `klaro.php` (Plugin header)
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