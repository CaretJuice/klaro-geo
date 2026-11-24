# Docker environment for Klaro Geo

Everything that needs WordPress runs here: the PHP test suite, and the site the Playwright tests and manual testing use. Run it through the two scripts in `bin/`, from any directory:

```bash
bin/phpunit.sh [phpunit arguments]    # the PHP test suite
bin/site.sh up | reset | down         # the site at http://localhost:8080
bin/site.sh wp <command>              # WP-CLI in the site
bin/site.sh log                       # follow the site's WordPress debug log
```

See [readme-dev.md](../readme-dev.md) for the day-to-day commands.

## Contents

- `Dockerfile` - the one image. It is the official WordPress image plus WP-CLI, WordPress's PHPUnit test library, the Geolocation IP Detection plugin and Plugin Check, each at a pinned version.
- `docker-compose.yml` - three services: `db`, `phpunit` (profile `test`) and `site` (profile `site`).
- `phpunit-entrypoint.sh` - gives a test run an empty database.
- `site-setup.sh` - installs WordPress and sets the state the Playwright tests start from.
- `php.ini` - PHP settings for the image.
- `logs/` - `debug_test.log`, the PHP errors and the plugin's debug log of the last PHPUnit run.

## How it works

- **Your checkout is mounted read-only**, as the plugin directory. No container changes the owner or permissions of your files. The PHPUnit container runs as you, and writes one file: `logs/debug_test.log`.
- **Starting a container downloads nothing.** Every tool is fetched when the image is built. The scripts rebuild the image when the Dockerfile changes, which takes a second when nothing has.
- **Nothing is kept.** The database is held in memory, and `bin/site.sh down` deletes the site's files. A start on your machine is the same as a start in CI. `bin/site.sh reset` returns a running site to a fresh install without restarting it.
- **PHPUnit comes from `vendor/`** on your machine (`composer install`), the same copy PHPCS and PHPStan use. WordPress and its test library come from the image, and are always the same version as each other.

## Other versions of WordPress and PHP

The image is built on `wordpress:<WP_VERSION>-php<PHP_VERSION>-apache`. Set both to try another pair:

```bash
WP_VERSION=6.8.3 PHP_VERSION=8.1 bin/phpunit.sh
```

Each pair is its own image tag, so switching back does not rebuild. The defaults are in `Dockerfile` and `docker-compose.yml`; keep the two in step.

## The debug log of a test run

`tests/phpunit/bootstrap.php` points PHP's error log at `logs/debug_test.log` and switches the plugin's debug logging on, so `klaro_geo_debug_log()` and `error_log()` both end up there. The file is emptied at the start of each run and written as the run goes, so it is complete when a test fails.

## Database

PHPUnit tests use a real database, emptied before each run. Tests that need the consent receipts tables create them once per test class with `klaro_geo_create_consent_receipts_table()`.
