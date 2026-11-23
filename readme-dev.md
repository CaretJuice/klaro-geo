# Development Setup for Klaro Geo

How to run the plugin locally and run its tests. Everything runs in Docker, so no local WordPress is needed. The rules the code follows, and which tests cover them, are in `CLAUDE.md`.

## Prerequisites

- Docker with Compose v2 (`docker compose`)
- Node.js and npm
- Git
- `pre-commit` (`pip install pre-commit`), then `pre-commit install` once after cloning

Run `npm install` once in the repository root. It installs Jest and Playwright; run `npm run e2e:install` once to download the Playwright browsers.

## Project structure

- `klaro-geo.php` - bootstrap, script loading, GTM output, shortcodes
- `includes/` - PHP: config generation, settings classes, receipts; `includes/admin/` holds the admin pages
- `js/`, `css/` - frontend and admin scripts and styles
- `specs/` - contracts with code outside this repository, and between PHP and JavaScript
- `tests/phpunit/`, `tests/js/`, `e2e/` - PHPUnit, Jest and Playwright tests
- `docker/` - the PHPUnit runner and the dev site
- `bin/check-banned-patterns.sh` - checks run by pre-commit

In every Docker stack the repository is mounted as the plugin directory, so edits are live without a restart.

## Running the plugin

Use the E2E site. It installs WordPress unattended, activates Klaro Geo and Geolocation IP Detection, and sets a GTM ID.

```bash
npm run e2e:setup          # start and wait until it answers
npm run e2e:docker:down    # stop
```

- Site: http://localhost:8080, admin at `/wp-admin` (`admin` / `password`)
- WP-CLI: `docker exec klaro-geo-wordpress-e2e wp --allow-root --path=/var/www/html <command>`

There is also a dev site (`cd docker && docker compose up -d wordpress`, http://localhost:8000). Its entrypoint changes the owner and permissions of the mounted plugin directory, which is your checkout, so prefer the E2E site.

## Running tests

```bash
# PHP (add --filter ClassName for one test class)
cd docker && docker compose run --rm wordpress_test vendor/bin/phpunit

# JavaScript, from the repository root, on the host
npx jest

# End to end
npm run e2e:setup
npm run e2e:test
npm run e2e:docker:down
```

The PHP test bootstrap turns the plugin's debug logging on. The log is written to `docker/logs/debug_test.log`, not to the terminal. More on the E2E tests is in `e2e/README.md`.

## Debug logging

Log in PHP with `klaro_geo_debug_log()` and in JavaScript with `klaroGeoLog()`, `klaroGeoWarn()` and `klaroGeoError()`. Both follow the plugin's "Enable debug logging" setting; PHP logging also needs `WP_DEBUG` to be true, and writes to the WordPress debug log.

## Building a release

```bash
./build-plugin.sh
```

It reads the version from the plugin header and writes `klaro-geo-<version>.zip` with the production files only. Run all three test suites first, and update the version in `klaro-geo.php` and `readme.txt`.

## How the Code Is Kept Honest

- **Tests carry the business rules.** PHPUnit tests are in `tests/phpunit/`, Jest tests in `tests/js/`, Playwright tests in `e2e/`. Tests call the plugin's real functions against the real test database; there is no mock database.
- **`specs/` holds contracts only**: the dataLayer events, the `window.klaroGeo.push()` queue API, the data PHP hands to JavaScript, and the client-side geo resolution that PHP and JavaScript both implement. If a change alters one of those, update the spec in the same commit. There are no per-file specs.
- **E2E tests run with a single worker.** They share one WordPress site and several of them change site-wide options, so they cannot run in parallel. A full run takes about two and a half minutes.

See `CLAUDE.md` for the full list of rules and which tests cover them.
