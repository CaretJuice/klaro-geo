<?php
/**
 * Configuration of the WordPress test library for this plugin's PHPUnit run.
 *
 * The library reads it from the path in WP_PHPUNIT__TESTS_CONFIG, which the
 * phpunit service sets (docker/docker-compose.yml). The database is the one
 * that service's entrypoint has just emptied.
 *
 * @package Klaro_Geo
 */

// WordPress core, as the image has it.
define( 'ABSPATH', '/usr/src/wordpress/' );

define( 'DB_NAME', getenv( 'WP_TESTS_DB_NAME' ) );
define( 'DB_USER', getenv( 'WP_TESTS_DB_USER' ) );
define( 'DB_PASSWORD', getenv( 'WP_TESTS_DB_PASSWORD' ) );
define( 'DB_HOST', getenv( 'WP_TESTS_DB_HOST' ) );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );

// The plugin's debug log needs WP_DEBUG. It is written to the one directory of
// the checkout the container can write to; see bootstrap.php.
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', dirname( __DIR__, 2 ) . '/docker/logs/debug_test.log' );
