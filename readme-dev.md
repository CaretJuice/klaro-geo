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
- `docker/` - the image and compose file behind `bin/phpunit.sh` and `bin/site.sh`
- `bin/` - the scripts that run the tests, the site and the checks

## Running the plugin

```bash
bin/site.sh up       # start the site; installs WordPress the first time
bin/site.sh reset    # back to a fresh install (what the Playwright tests start from)
bin/site.sh down     # stop it and delete its data
```

- Site: http://localhost:8080, admin at `/wp-admin` (`admin` / `password`). Klaro Geo and Geolocation IP Detection are active and a GTM ID is set.
- WP-CLI: `bin/site.sh wp <command>`
- WordPress debug log: `bin/site.sh log`

Your checkout is mounted as the plugin, read-only, so edits are live without a restart and nothing in a container changes your files. Nothing is kept when the site is taken down; every start is a clean install. See `docker/readme.md` for how the environment is built.

## Running tests

```bash
# PHP (add --filter ClassName for one test class)
bin/phpunit.sh

# JavaScript, from the repository root, on the host
npx jest

# End to end
npm run e2e:setup
npm run e2e:test
npm run site:down
```

The PHP test bootstrap turns the plugin's debug logging on. The log of the last run is `docker/logs/debug_test.log`, not the terminal. More on the E2E tests is in `e2e/README.md`.

## Static checks

After `composer install`:

```bash
vendor/bin/phpcs                       # WordPress coding standards, PHP 7.2 / WordPress 5.7 compatibility
vendor/bin/phpcbf                      # fixes what phpcs can fix automatically
vendor/bin/phpstan analyse             # type checks
bin/check-banned-patterns.sh           # patterns this project has ruled out (see CLAUDE.md)
npm run plugin-check                   # WordPress.org's Plugin Check, run on the build output
```

pre-commit runs the first three on commits that change PHP. CI runs them too, and runs Plugin Check, PHPUnit, Jest and the Playwright suite (`.github/workflows/checks.yml`).

## Debug logging

Log in PHP with `klaro_geo_debug_log()` and in JavaScript with `klaroGeoLog()`, `klaroGeoWarn()` and `klaroGeoError()`. Both follow the plugin's "Enable debug logging" setting; PHP logging also needs `WP_DEBUG` to be true, and writes to the WordPress debug log.

## Building a release

```bash
./build-plugin.sh
```

It reads the version from the plugin header and writes `klaro-geo-<version>.zip` with the production files only. Run all three test suites and `npm run plugin-check` first, and update the version in `klaro-geo.php` and `readme.txt` (`Tested up to` lives in `readme.txt` only, and must be the current WordPress release).

## How the Code Is Kept Honest

- **Tests carry the business rules.** PHPUnit tests are in `tests/phpunit/`, Jest tests in `tests/js/`, Playwright tests in `e2e/`. Tests call the plugin's real functions against the real test database; there is no mock database.
- **`specs/` holds contracts only**: the dataLayer events, the `window.klaroGeo.push()` queue API, the data PHP hands to JavaScript, and the client-side geo resolution that PHP and JavaScript both implement. If a change alters one of those, update the spec in the same commit. There are no per-file specs.
- **E2E tests run with a single worker.** They share one WordPress site and several of them change site-wide options, so they cannot run in parallel. A full run takes about two and a half minutes.

See `CLAUDE.md` for the full list of rules and which tests cover them.
