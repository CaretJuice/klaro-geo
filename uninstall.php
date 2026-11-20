<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * @package Klaro_Geo
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/klaro-geo-uninstall.php';

klaro_geo_uninstall();
