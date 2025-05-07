# Klaro Geo JavaScript Tests

This directory contains JavaScript tests for the Klaro Geo plugin.

## Test Files

- `test-klaro-geo.js`: Tests for the core functionality in `klaro-geo.js`
- `setup.js`: Setup file for Jest
- `run-tests.js`: Custom test runner

## Running Tests

You can run the tests using npm:

```bash
# Run all tests
npm test

# Run only the klaro-geo tests
npm run test:klaro-geo

# Run tests in watch mode (automatically re-run when files change)
npm run test:watch
```

## Test Structure

The tests are organized by functionality:

1. **Consent Receipt Handling**
   - `handleConsentChange`
   - `storeReceiptLocally`
   - `sendReceiptToServer`

2. **DataLayer Integration**
   - `pushConsentData`
   - `handleKlaroConsentEvents`

3. **Google Consent Mode**
   - `updateGoogleConsentMode`

4. **UI Controls**
   - `createToggleControl`
   - `createAdControlsForService`
   - `updateControlsUI`

## Adding New Tests

To add new tests:

1. Add test cases to the appropriate test file
2. If testing a new module, create a new test file following the naming convention `test-*.js`

## Test Coverage

The tests aim to cover all the main functionality in the JavaScript files. Run the tests with coverage to see what's covered:

```bash
npm test -- --coverage
```