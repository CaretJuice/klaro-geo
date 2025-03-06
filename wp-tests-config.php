<?php
// Test with WordPress debug mode (default).
define('WP_DEBUG', true);

// ** MySQL settings ** //
define('DB_NAME', 'wordpress_test');
define('DB_USER', 'wordpress');
define('DB_PASSWORD', 'wordpress');
define('DB_HOST', 'db_test');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

$table_prefix = 'wptests_';

define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'Test Blog');

define('WP_PHP_BINARY', 'php');
define('WPLANG', '');

// This is where WordPress core files should be
define('ABSPATH', '/var/www/html/');

// Test with WordPress debug mode (default).
define('WP_DEBUG', true);

// ** Additional settings as needed ** //
define('WP_TESTS_MULTISITE', false);

// Force known bugs to be run.
define('WP_TESTS_FORCE_KNOWN_BUGS', true);
