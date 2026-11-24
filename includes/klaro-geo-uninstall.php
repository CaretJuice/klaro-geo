<?php
/**
 * Removes the plugin's data when it is deleted.
 *
 * Loaded only by uninstall.php (and by the tests). WordPress runs uninstall.php
 * without loading the plugin, so nothing here may rely on other plugin files.
 *
 * @package Klaro_Geo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every option the plugin stores, current and from earlier versions.
 *
 * KlaroGeoUninstallTest fails if a setting is registered without being added
 * here.
 *
 * @return string[]
 */
function klaro_geo_uninstall_option_names() {
	return array(
		// Templates, countries, services and purposes.
		'klaro_geo_templates',
		'klaro_geo_country_settings',
		'klaro_geo_visible_countries',
		'klaro_geo_services',
		'klaro_geo_purposes',
		'klaro_geo_analytics_purposes',
		'klaro_geo_ad_purposes',

		// Google Tag Manager and consent mode.
		'klaro_geo_gtm_id',
		'klaro_geo_consent_mode_type',
		'klaro_geo_suppress_consents_events',
		'klaro_geo_gtm_consent_migrated',
		'klaro_geo_gtm_purpose_migrated',

		// Geo resolution.
		'klaro_geo_geo_resolution_mode',
		'klaro_geo_client_geo_timeout_ms',
		'klaro_geo_client_geo_timezone_fallback',
		'klaro_geo_fallback_behavior',

		// Global Privacy Control.
		'klaro_geo_gpc_enabled',
		'klaro_geo_gpc_well_known',
		'klaro_geo_gpc_well_known_updated',

		// Consent receipts. The receipts table is handled separately.
		'klaro_geo_enable_consent_receipts',
		'klaro_geo_consent_receipts_db_version',
		'klaro_geo_delete_receipts_on_uninstall',

		// Floating button.
		'klaro_geo_enable_floating_button',
		'klaro_geo_floating_button_text',
		'klaro_geo_floating_button_theme',
		'klaro_geo_floating_button_position',

		// Debugging and cleanup.
		'klaro_geo_debug_countries',
		'klaro_geo_enable_debug_logging',
		'klaro_geo_cleanup_on_deactivate',

		// Options written by earlier versions only.
		'klaro_geo_version',
		'klaro_geo_js_version',
		'klaro_geo_js_variant',
		'klaro_geo_button_text',
		'klaro_geo_button_theme',
		'klaro_geo_debug_geo',
		'klaro_geo_consent_mode_settings',
		'klaro_geo_analytics_storage_service',
		'klaro_geo_initialization_code',
		'klaro_geo_gtm_oninit',
		'klaro_geo_gtm_onaccept',
		'klaro_geo_gtm_ondecline',
		'klaro_geo_elementID',
		'klaro_geo_styling_theme',
		'klaro_geo_consent_modal_show_widget',
		'klaro_geo_consent_modal_title',
		'klaro_geo_consent_modal_description',
		'klaro_geo_consent_modal_accept_all_button_text',
		'klaro_geo_consent_modal_decline_all_button_text',
		'klaro_geo_consent_modal_learn_more_button_text',
		'klaro_geo_consent_modal_accept_button_text',
		'klaro_geo_consent_modal_decline_button_text',
		'klaro_geo_noAutoLoad',
		'klaro_geo_htmlTexts',
		'klaro_geo_embedded',
		'klaro_geo_groupByPurpose',
		'klaro_geo_storageMethod',
		'klaro_geo_cookieName',
		'klaro_geo_cookieDomain',
		'klaro_geo_cookieExpiresAfterDays',
		'klaro_geo_default',
		'klaro_geo_mustConsent',
		'klaro_geo_acceptAll',
		'klaro_geo_hideDeclineAll',
		'klaro_geo_hideLearnMore',
		'klaro_geo_noticeAsModal',
	);
}

/**
 * Remove the plugin's data from the current site.
 *
 * Consent receipts are a record of what visitors agreed to, so the table is
 * dropped only if the site owner chose that on the settings page. uninstall.php
 * runs unattended and cannot ask at the time.
 */
function klaro_geo_uninstall_site() {
	global $wpdb;

	// Read before the options are deleted.
	$delete_receipts = (bool) get_option( 'klaro_geo_delete_receipts_on_uninstall', false );

	foreach ( klaro_geo_uninstall_option_names() as $option ) {
		delete_option( $option );
	}

	delete_transient( 'klaro_geo_geoip_ajax_probe' );

	// Receipt rate limit transients, one per visitor IP hash. With a persistent
	// object cache they are not in this table and expire on their own.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Deletes this plugin's transients by name prefix, which no WordPress function does; the statement is prepared.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_klaro_geo_rl_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_klaro_geo_rl_' ) . '%'
		)
	);

	if ( $delete_receipts ) {
		$table_name = $wpdb->prefix . 'klaro_geo_consent_receipts';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DDL statement; table name from $wpdb->prefix is trusted and cannot be parameterized.
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
	}
}

/**
 * Remove the plugin's data from every site, and its per-user data.
 */
function klaro_geo_uninstall() {
	if ( is_multisite() ) {
		foreach ( get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) as $site_id ) {
			switch_to_blog( $site_id );
			klaro_geo_uninstall_site();
			restore_current_blog();
		}
	} else {
		klaro_geo_uninstall_site();
	}

	// Dismissal of the GeoIP AJAX warning, stored per administrator.
	delete_metadata( 'user', 0, 'klaro_geo_dismiss_geoip_ajax_warning', '', true );
}
