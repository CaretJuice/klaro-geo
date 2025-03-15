# Running Tests for Klaro Geo

This document explains how to run tests for the Klaro Geo plugin.

## Quick Start

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

## Available Test Scripts

### No-Password Scripts (Recommended)

These scripts bypass the Docker entrypoint script that requires a password:

- `run-php-tests.sh` - Runs all PHP tests
- `run-js-tests.sh` - Runs all JavaScript tests
- `run-js-tests.sh "test-name"` - Runs a specific JavaScript test

### Direct Test Script (Most Reliable)

If you're still having issues with npm permissions, use the direct test script:

- `run-js-tests-direct.sh` - Runs JavaScript tests in a fresh container without using the existing npm cache
- `run-js-tests-direct.sh "test-name"` - Runs a specific JavaScript test in a fresh container

### Original Scripts (May Require Password)

The original test scripts may prompt for a password:

- `run-tests.sh` - Runs all PHP tests
- `run-tests.sh js` - Runs all JavaScript tests
- `run-tests.sh js "test-name"` - Runs a specific JavaScript test

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
./docker/view-test-logs.sh
```

This will display:
- WordPress debug logs
- npm logs
- Jest test output

The logs are also saved to the `docker/logs` directory.

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