<?php
/**
 * wp-config.php of the site container (docker/Dockerfile). A development
 * site on a database that is thrown away: nothing here is a secret.
 */

define( 'DB_NAME', getenv( 'WORDPRESS_DB_NAME' ) );
define( 'DB_USER', getenv( 'WORDPRESS_DB_USER' ) );
define( 'DB_PASSWORD', getenv( 'WORDPRESS_DB_PASSWORD' ) );
define( 'DB_HOST', getenv( 'WORDPRESS_DB_HOST' ) );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wp_';

foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $klaro_geo_key ) {
	define( $klaro_geo_key, 'klaro-geo-development-' . $klaro_geo_key );
}

// The plugin's debug log needs WP_DEBUG. The log is wp-content/debug.log in
// the container: bin/site.sh log.
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
