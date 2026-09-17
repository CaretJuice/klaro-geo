<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin warning for client-side geo mode when the GeoIP Detection plugin's
 * AJAX endpoint is unavailable.
 *
 * Detection is layered and best-effort (spec: client-side-geo.spec.yaml,
 * admin_warnings section). It FAILS OPEN: when no layer is conclusive, no
 * warning is shown — runtime behavior is still protected by the resolver's
 * fallback chain (timezone → fallback template).
 */

/**
 * Determine the status of geoip-detect's AJAX endpoint.
 *
 * Layers (first conclusive result wins):
 *   1. Is geoip-detect active at all?
 *   2. geoip-detect's persisted option ('geoip-detect-ajax_enabled' as of
 *      geoip-detect 2.x/5.x — best-known key, not guaranteed API).
 *   3. Functional probe: loopback request to the AJAX action, cached in a
 *      transient for an hour.
 *
 * @return string 'ok' | 'disabled' | 'plugin-missing' | 'unknown'
 */
function klaro_geo_geoip_ajax_endpoint_status() {
	// Layer 1: plugin present?
	if ( ! function_exists( 'geoip_detect2_get_info_from_current_ip' ) ) {
		return 'plugin-missing';
	}

	// Layer 2: option row. A sentinel default distinguishes "stored falsy"
	// (conclusive: disabled) from "option row missing" (inconclusive).
	$sentinel     = '__klaro_geo_option_missing__';
	$option_value = get_option( 'geoip-detect-ajax_enabled', $sentinel );
	if ( $sentinel !== $option_value ) {
		return empty( $option_value ) ? 'disabled' : 'ok';
	}

	// Layer 3: functional probe (cached — loopback requests are not free).
	$cached = get_transient( 'klaro_geo_geoip_ajax_probe' );
	if ( false !== $cached ) {
		return $cached;
	}

	$response = wp_remote_post(
		admin_url( 'admin-ajax.php' ),
		array(
			'timeout' => 5,
			'body'    => array( 'action' => 'geoip_detect2_get_info_from_current_ip' ),
		)
	);

	if ( is_wp_error( $response ) ) {
		// Transport failure (loopback blocked, etc.): inconclusive → fail open.
		$status = 'unknown';
	} else {
		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		// A working endpoint returns HTTP 200 with a geoip-detect record shape.
		if ( 200 === $code && is_array( $decoded )
			&& ( isset( $decoded['country'] ) || isset( $decoded['is_empty'] ) || isset( $decoded['extra'] ) ) ) {
			$status = 'ok';
		} else {
			// admin-ajax answers 400/0 for unregistered actions → endpoint off.
			$status = 'disabled';
		}
	}

	set_transient( 'klaro_geo_geoip_ajax_probe', $status, HOUR_IN_SECONDS );
	return $status;
}

/**
 * Invalidate the probe cache when the resolution mode changes, so the notice
 * reflects reality immediately after enabling client mode.
 */
function klaro_geo_geoip_ajax_probe_invalidate() {
	delete_transient( 'klaro_geo_geoip_ajax_probe' );
}
add_action( 'update_option_klaro_geo_geo_resolution_mode', 'klaro_geo_geoip_ajax_probe_invalidate' );
add_action( 'add_option_klaro_geo_geo_resolution_mode', 'klaro_geo_geoip_ajax_probe_invalidate' );

/**
 * Handle the per-user dismissal link.
 */
function klaro_geo_geoip_ajax_warning_dismiss() {
	if ( ! isset( $_GET['klaro_geo_dismiss_geoip_ajax_warning'] ) ) {
		return;
	}
	if ( ! isset( $_GET['_wpnonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'klaro_geo_dismiss_geoip_ajax_warning' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	update_user_meta( get_current_user_id(), 'klaro_geo_dismiss_geoip_ajax_warning', time() );
}
add_action( 'admin_init', 'klaro_geo_geoip_ajax_warning_dismiss' );

/**
 * Show the warning on Klaro Geo admin pages when client mode is on and the
 * AJAX endpoint is conclusively unavailable.
 */
function klaro_geo_geoip_ajax_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Klaro Geo admin pages only (not site-wide)
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || strpos( $screen->id, 'klaro-geo' ) === false ) {
		return;
	}

	// Only relevant in client-side geo mode
	if ( get_option( 'klaro_geo_geo_resolution_mode', 'server' ) !== 'client' ) {
		return;
	}

	// Per-user dismissal
	if ( get_user_meta( get_current_user_id(), 'klaro_geo_dismiss_geoip_ajax_warning', true ) ) {
		return;
	}

	$status = klaro_geo_geoip_ajax_endpoint_status();
	if ( 'ok' === $status || 'unknown' === $status ) {
		// 'unknown' fails open: no warning without a conclusive signal.
		return;
	}

	$dismiss_url = wp_nonce_url(
		add_query_arg( 'klaro_geo_dismiss_geoip_ajax_warning', '1' ),
		'klaro_geo_dismiss_geoip_ajax_warning'
	);

	if ( 'plugin-missing' === $status ) {
		$message = 'Klaro Geo is set to <strong>client-side geo resolution</strong>, but the '
			. '<a href="https://wordpress.org/plugins/geoip-detect/" target="_blank" rel="noopener noreferrer">Geolocation IP Detection</a> '
			. 'plugin is not active. Until it is installed and active with its AJAX endpoint enabled, visitors are resolved by the '
			. 'timezone fallback (country-level accuracy at best) or receive the fallback consent template.';
	} else {
		$message = 'Klaro Geo is set to <strong>client-side geo resolution</strong>, which requires the Geolocation IP Detection plugin\'s AJAX endpoint — '
			. 'but it appears to be disabled. Enable it under <strong>Settings &rarr; Geolocation IP Detection &rarr; "Enable AJAX endpoint"</strong>. '
			. 'Until then, visitors are resolved by the timezone fallback (country-level accuracy at best) or receive the fallback consent template.';
	}

	echo '<div class="notice notice-warning"><p>'
		. wp_kses(
			$message,
			array(
				'strong' => array(),
				'a'      => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
			)
		)
		. ' <a href="' . esc_url( $dismiss_url ) . '">Dismiss this notice</a>.</p></div>';
}
add_action( 'admin_notices', 'klaro_geo_geoip_ajax_admin_notice' );
