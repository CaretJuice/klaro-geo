<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Klaro Geo Consent Receipts
 *
 * Handles the creation, storage, and retrieval of consent receipts
 */

/**
 * Database helper functions for the consent receipts custom table.
 * Centralizes all direct $wpdb calls to reduce Plugin Check surface area.
 */

/**
 * Get the consent receipts table name.
 */
function klaro_geo_receipts_table_name() {
	global $wpdb;
	return $wpdb->prefix . 'klaro_geo_consent_receipts';
}

/**
 * Check if the consent receipts table exists.
 */
function klaro_geo_receipts_table_exists() {
	global $wpdb;
	$table_name = klaro_geo_receipts_table_name();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table existence check; table name from $wpdb->prefix is trusted.
	return $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
}

/**
 * Check if a column exists in the consent receipts table.
 */
function klaro_geo_receipts_column_exists( $column ) {
	global $wpdb;
	$table_name = klaro_geo_receipts_table_name();
	$column     = sanitize_key( $column );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema check; table name from $wpdb->prefix is trusted, column name sanitized.
	return ! empty( $wpdb->get_results( "SHOW COLUMNS FROM $table_name LIKE '$column'" ) );
}

/**
 * Insert a receipt row into the consent receipts table.
 */
function klaro_geo_receipts_insert( $data ) {
	global $wpdb;
	$table_name = klaro_geo_receipts_table_name();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert; data is sanitized by callers.
	return $wpdb->insert( $table_name, $data );
}

/**
 * Get the total number of consent receipts.
 */
function klaro_geo_receipts_count() {
	global $wpdb;
	$table_name = klaro_geo_receipts_table_name();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Count query on custom table; table name from $wpdb->prefix is trusted.
	return $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
}

/**
 * Get paginated consent receipts.
 */
function klaro_geo_receipts_get_page( $per_page, $offset ) {
	global $wpdb;
	$table_name = klaro_geo_receipts_table_name();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Paginated query on custom table; not suitable for object caching.
	return $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix is trusted.
			"SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT %d OFFSET %d",
			$per_page,
			$offset
		)
	);
}

/**
 * Get a single receipt by ID.
 */
function klaro_geo_receipts_get_by_id( $receipt_id ) {
	global $wpdb;
	$table_name = klaro_geo_receipts_table_name();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Single row lookup on custom table.
	return $wpdb->get_row(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix is trusted.
			"SELECT * FROM $table_name WHERE id = %d",
			$receipt_id
		)
	);
}

/**
 * Check if a receipt_id already exists (duplicate check).
 */
function klaro_geo_receipts_exists( $receipt_id ) {
	global $wpdb;
	$table_name = klaro_geo_receipts_table_name();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Duplicate check before insert.
	$count = $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix is trusted.
			"SELECT COUNT(*) FROM $table_name WHERE receipt_id = %s",
			$receipt_id
		)
	);
	return intval( $count ) > 0;
}

/**
 * Schema version of the receipts table, stored in
 * klaro_geo_consent_receipts_db_version. Raise it when a column is added and
 * add the column to klaro_geo_upgrade_consent_receipts_table().
 */
define( 'KLARO_GEO_RECEIPTS_DB_VERSION', '1.2' );

/**
 * Create the receipts table, or bring an existing one up to the current
 * schema. Runs on activation, when the stored schema version is behind (see
 * klaro_geo_upgrade_consent_receipts_table()) and when an insert fails.
 *
 * Columns added after 1.0 are listed in $added. Receipts stored before a
 * column existed keep NULL in it: for gpc_signal that means "not recorded",
 * which is not the same as "no signal".
 *
 * @return bool Whether the table exists with the current schema.
 */
function klaro_geo_create_consent_receipts_table() {
	global $wpdb;
	$table_name = klaro_geo_receipts_table_name();

	// Columns added since schema 1.0, in the order they were added.
	$added = array(
		'klaro_config'          => 'longtext DEFAULT NULL',
		'gpc_signal'            => 'tinyint(1) DEFAULT NULL',       // 1.1
		'gpc_affected_services' => 'text DEFAULT NULL',             // 1.1
		'receipt_type'          => 'varchar(20) DEFAULT NULL',      // 1.2
	);

	if ( ! klaro_geo_receipts_table_exists() ) {
		$added_sql = '';
		foreach ( $added as $column => $definition ) {
			$added_sql .= "$column $definition,\n";
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			receipt_id varchar(50) NOT NULL,
			user_id bigint(20) DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			timestamp datetime NOT NULL,
			consent_data longtext NOT NULL,
			template_name varchar(100) NOT NULL,
			template_source varchar(50) NOT NULL,
			country_code varchar(10) DEFAULT NULL,
			region_code varchar(10) DEFAULT NULL,
			user_agent text DEFAULT NULL,
			$added_sql PRIMARY KEY  (id),
			KEY receipt_id (receipt_id),
			KEY user_id (user_id),
			KEY timestamp (timestamp),
			KEY country_code (country_code)
			) " . $wpdb->get_charset_collate()
		);

		if ( ! klaro_geo_receipts_table_exists() ) {
			klaro_geo_debug_log( "Could not create $table_name: " . $wpdb->last_error );
			return false;
		}
	} else {
		foreach ( $added as $column => $definition ) {
			if ( klaro_geo_receipts_column_exists( $column ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL statement; table name from $wpdb->prefix and the column definitions above are trusted and cannot be parameterized.
			if ( false === $wpdb->query( "ALTER TABLE $table_name ADD COLUMN $column $definition" ) ) {
				klaro_geo_debug_log( "Could not add column $column to $table_name: " . $wpdb->last_error );
				return false; // The version is left as it was, so this is tried again.
			}
		}
	}

	update_option( 'klaro_geo_consent_receipts_db_version', KLARO_GEO_RECEIPTS_DB_VERSION );
	return true;
}

/**
 * Upgrade the receipts table after a plugin update that changed its schema.
 */
function klaro_geo_upgrade_consent_receipts_table() {
	$version = get_option( 'klaro_geo_consent_receipts_db_version' );

	// No version means the table was never created; activation or the first
	// receipt creates it with the current schema.
	if ( false !== $version && KLARO_GEO_RECEIPTS_DB_VERSION !== $version ) {
		klaro_geo_create_consent_receipts_table();
	}
}
add_action( 'init', 'klaro_geo_upgrade_consent_receipts_table' );

// Drop the consent receipts table and forget its schema version.
function klaro_geo_drop_consent_receipts_table() {
	global $wpdb;
	$table_name = klaro_geo_receipts_table_name();

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL statement; table name from $wpdb->prefix is trusted and cannot be parameterized.
	$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
	delete_option( 'klaro_geo_consent_receipts_db_version' );
}

// On deactivation the table is dropped only when "Remove all plugin settings when deactivating" is on.
function klaro_geo_drop_consent_receipts_table_on_deactivate() {
	if ( get_option( 'klaro_geo_cleanup_on_deactivate', false ) ) {
		klaro_geo_drop_consent_receipts_table();
	}
}

// Generate a unique receipt ID based on timestamp and random string
function klaro_geo_generate_receipt_id() {
	$timestamp = microtime( true );
	$random    = bin2hex( random_bytes( 8 ) ); // 16 character random string
	return 'receipt_' . str_replace( '.', '', $timestamp ) . '_' . $random;
}

/**
 * Validate and sanitize a consent receipt submitted by a browser.
 *
 * Everything in $raw is untrusted: the endpoint is public and takes requests
 * without a nonce. Only the shapes below are accepted; nothing is stored as
 * submitted.
 *
 * @param mixed $raw The decoded receipt.
 * @return array|WP_Error Sanitized receipt, or an error describing the rejection.
 */
function klaro_geo_sanitize_receipt( $raw ) {
	if ( ! is_array( $raw ) ) {
		return new WP_Error( 'klaro_geo_invalid_receipt', 'Invalid receipt data' );
	}

	$receipt_id = isset( $raw['receipt_id'] ) && is_string( $raw['receipt_id'] ) ? $raw['receipt_id'] : '';
	if ( ! preg_match( '/^[A-Za-z0-9_\-]{1,50}$/', $receipt_id ) ) {
		return new WP_Error( 'klaro_geo_invalid_receipt', 'Invalid receipt ID' );
	}

	// Consent choices: service name => boolean, nothing else.
	if ( ! isset( $raw['consent_choices'] ) || ! is_array( $raw['consent_choices'] ) || count( $raw['consent_choices'] ) > 200 ) {
		return new WP_Error( 'klaro_geo_invalid_receipt', 'Invalid consent choices' );
	}
	$consent_choices = array();
	foreach ( $raw['consent_choices'] as $service_name => $granted ) {
		$service_name = sanitize_text_field( (string) $service_name );
		if ( '' === $service_name || strlen( $service_name ) > 100 ) {
			return new WP_Error( 'klaro_geo_invalid_receipt', 'Invalid consent choices' );
		}
		$consent_choices[ $service_name ] = ( true === $granted );
	}

	$text = function ( $key, $max_length ) use ( $raw ) {
		$value = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? sanitize_text_field( (string) $raw[ $key ] ) : '';
		return substr( $value, 0, $max_length );
	};

	$region_code     = $text( 'region_code', 10 );
	$template_source = $text( 'template_source', 50 );

	$admin_override = isset( $raw['admin_override'] ) && in_array( $raw['admin_override'], array( true, 'true', 1, '1' ), true );
	if ( 0 === strpos( $template_source, 'admin-override' ) ) {
		$admin_override = true;
	}

	// Normalize the source to "<admin-override|geo-match> <country|region>"; 'fallback' is kept as is.
	if ( 'fallback' !== $template_source ) {
		$template_source = ( $admin_override ? 'admin-override' : 'geo-match' ) . ' ' . ( '' !== $region_code ? 'region' : 'country' );
	}

	$timestamp = isset( $raw['timestamp'] ) && is_numeric( $raw['timestamp'] ) ? absint( $raw['timestamp'] ) : 0;

	$receipt = array(
		'receipt_id'      => $receipt_id,
		'timestamp'       => $timestamp > 0 ? $timestamp : time(),
		'consent_choices' => $consent_choices,
		'template_name'   => $text( 'template_name', 100 ),
		'template_source' => $template_source,
		'country_code'    => $text( 'country_code', 10 ),
		'region_code'     => $region_code,
		'admin_override'  => $admin_override,
		'klaro_config'    => null,
		// Global Privacy Control: whether the browser reported the signal, and
		// the services the plugin switched off by default because of it.
		'gpc_signal'            => isset( $raw['gpc_signal'] ) && true === $raw['gpc_signal'],
		'gpc_affected_services' => array(),
		// 'choice': the visitor saved a choice in the banner. 'gpc': GPC was
		// applied and the visitor had not chosen (see recordGpcReceipt() in
		// js/klaro-geo.js).
		'receipt_type'          => ( isset( $raw['receipt_type'] ) && 'gpc' === $raw['receipt_type'] ) ? 'gpc' : 'choice',
	);

	if ( isset( $raw['gpc_affected_services'] ) && is_array( $raw['gpc_affected_services'] ) && count( $raw['gpc_affected_services'] ) <= 200 ) {
		foreach ( $raw['gpc_affected_services'] as $service_name ) {
			$service_name = is_string( $service_name ) ? sanitize_text_field( $service_name ) : '';
			if ( '' !== $service_name && strlen( $service_name ) <= 100 ) {
				$receipt['gpc_affected_services'][] = $service_name;
			}
		}
	}

	// The Klaro config snapshot is optional. Strings are stripped of markup, and a
	// snapshot too large to store whole is dropped rather than cut mid-JSON.
	if ( isset( $raw['klaro_config'] ) && is_array( $raw['klaro_config'] ) ) {
		$config_json = wp_json_encode( klaro_geo_sanitize_array( $raw['klaro_config'] ) );
		if ( false !== $config_json && strlen( $config_json ) <= 65000 ) {
			$receipt['klaro_config'] = $config_json;
		} else {
			klaro_geo_debug_log( 'klaro_config snapshot dropped from receipt ' . $receipt_id . ' (too large or not encodable)' );
		}
	}

	return $receipt;
}

/**
 * Whether consent receipts are being recorded for a template.
 *
 * Requires the global setting, and the template must not have consent logging
 * switched off. This mirrors the effective enableConsentLogging value the
 * frontend receives (see klaro_geo_build_consent_data()).
 *
 * @param string $template_name Template key the receipt was generated under.
 * @return bool
 */
function klaro_geo_is_consent_logging_enabled( $template_name ) {
	if ( ! get_option( 'klaro_geo_enable_consent_receipts', false ) ) {
		return false;
	}

	$template = Klaro_Geo_Template_Settings::get_instance()->get_template( $template_name );
	if ( is_array( $template ) && isset( $template['plugin_settings']['enable_consent_logging'] ) ) {
		return (bool) $template['plugin_settings']['enable_consent_logging'];
	}

	return true;
}

/**
 * Validate and store a consent receipt.
 *
 * The single implementation of receipt storage; the AJAX handler is a thin
 * wrapper around it.
 *
 * @param mixed $raw     The decoded receipt as submitted.
 * @param int   $user_id User to record the receipt against, from
 *                       klaro_geo_receipt_user_id(). 0 records it as anonymous.
 * @return int|WP_Error Row ID of the stored receipt, or an error. Error code
 *                      'klaro_geo_duplicate_receipt' means the receipt_id was
 *                      already stored.
 */
function klaro_geo_store_consent_receipt( $raw, $user_id = 0 ) {
	$receipt = klaro_geo_sanitize_receipt( $raw );
	if ( is_wp_error( $receipt ) ) {
		return $receipt;
	}

	if ( ! klaro_geo_is_consent_logging_enabled( $receipt['template_name'] ) ) {
		return new WP_Error( 'klaro_geo_logging_disabled', 'Consent logging is disabled' );
	}

	if ( klaro_geo_receipts_exists( $receipt['receipt_id'] ) ) {
		klaro_geo_debug_log( 'Duplicate receipt_id detected: ' . $receipt['receipt_id'] );
		return new WP_Error( 'klaro_geo_duplicate_receipt', 'Duplicate receipt ID skipped' );
	}

	$user_id = absint( $user_id );

	$data = array(
		'receipt_id'      => $receipt['receipt_id'],
		'user_id'         => $user_id ? $user_id : null,
		'ip_address'      => klaro_geo_get_anonymized_ip(),
		'timestamp'       => gmdate( 'Y-m-d H:i:s', $receipt['timestamp'] ),
		'consent_data'    => wp_json_encode( $receipt['consent_choices'] ),
		'template_name'   => $receipt['template_name'],
		'template_source' => $receipt['template_source'],
		'country_code'    => $receipt['country_code'],
		'region_code'     => $receipt['region_code'],
		'user_agent'      => klaro_geo_get_coarse_user_agent(),
	);

	// A browser with GPC on also sends "Sec-GPC: 1" with the request, so the
	// signal is recorded when either the receipt or the request reports it.
	$data['gpc_signal']            = ( $receipt['gpc_signal'] || klaro_geo_request_has_gpc_header() ) ? 1 : 0;
	$data['gpc_affected_services'] = wp_json_encode( $receipt['gpc_affected_services'] );
	$data['receipt_type']          = $receipt['receipt_type'];

	if ( null !== $receipt['klaro_config'] ) {
		$data['klaro_config'] = $receipt['klaro_config'];
	}

	global $wpdb;
	$inserted = klaro_geo_receipts_insert( $data );
	if ( false === $inserted ) {
		// The table is missing or behind the current schema. Create or
		// upgrade it and try once more, so the receipt is not lost.
		klaro_geo_debug_log( 'Receipt insert failed (' . $wpdb->last_error . '); creating or upgrading the receipts table and retrying' );
		$inserted = klaro_geo_create_consent_receipts_table() ? klaro_geo_receipts_insert( $data ) : false;
	}

	if ( false === $inserted ) {
		// The database error is logged, never returned: this is a public endpoint.
		klaro_geo_debug_log( 'Database error when inserting consent receipt: ' . $wpdb->last_error );
		return new WP_Error( 'klaro_geo_storage_failed', 'Could not store consent receipt' );
	}

	klaro_geo_debug_log( 'Stored consent receipt ' . $receipt['receipt_id'] . ' (row ' . $wpdb->insert_id . ', source: ' . $receipt['template_source'] . ')' );

	return (int) $wpdb->insert_id;
}

/**
 * Whether the current request carries the Global Privacy Control header.
 *
 * @return bool
 */
function klaro_geo_request_has_gpc_header() {
	return isset( $_SERVER['HTTP_SEC_GPC'] ) && '1' === trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_GPC'] ) ) );
}

// Get an anonymized IP address for privacy
function klaro_geo_get_anonymized_ip() {
	$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

	// If IPv4, anonymize the last octet
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		return preg_replace( '/\.\d+$/', '.0', $ip );
	}

	// If IPv6, anonymize the last 80 bits (last 20 hex chars)
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
		return preg_replace( '/:[0-9a-f]{1,4}(:[0-9a-f]{1,4}){4}$/i', ':0:0:0:0:0', $ip );
	}

	return $ip;
}

// Get a coarse user agent string for privacy (avoids fingerprinting)
// Returns format like "Chrome Mobile/Android" or "Safari/macOS"
function klaro_geo_get_coarse_user_agent() {
	$ua = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
	if ( empty( $ua ) ) {
		return 'unknown';
	}

	// Detect browser
	$browser = 'Other';
	if ( preg_match( '/Edg(e|A|iOS)?\//', $ua ) ) {
		$browser = 'Edge';
	} elseif ( preg_match( '/OPR\/|Opera/', $ua ) ) {
		$browser = 'Opera';
	} elseif ( preg_match( '/SamsungBrowser\//', $ua ) ) {
		$browser = 'Samsung Internet';
	} elseif ( preg_match( '/Chrome\//', $ua ) && ! preg_match( '/Chromium\//', $ua ) ) {
		$browser = 'Chrome';
	} elseif ( preg_match( '/Safari\//', $ua ) && ! preg_match( '/Chrome\//', $ua ) ) {
		$browser = 'Safari';
	} elseif ( preg_match( '/Firefox\//', $ua ) ) {
		$browser = 'Firefox';
	}

	// Detect device type
	$device = 'Desktop';
	if ( preg_match( '/Mobi|Android.*Mobile|iPhone|iPod/', $ua ) ) {
		$device = 'Mobile';
	} elseif ( preg_match( '/Tablet|iPad|Android(?!.*Mobile)/', $ua ) ) {
		$device = 'Tablet';
	}

	// Detect OS family
	$os = 'Other';
	if ( preg_match( '/Windows/', $ua ) ) {
		$os = 'Windows';
	} elseif ( preg_match( '/Macintosh|Mac OS X/', $ua ) ) {
		$os = 'macOS';
	} elseif ( preg_match( '/iPhone|iPad|iPod/', $ua ) ) {
		$os = 'iOS';
	} elseif ( preg_match( '/Android/', $ua ) ) {
		$os = 'Android';
	} elseif ( preg_match( '/Linux/', $ua ) ) {
		$os = 'Linux';
	} elseif ( preg_match( '/CrOS/', $ua ) ) {
		$os = 'ChromeOS';
	}

	// Combine device type only if not Desktop (Desktop is implied)
	if ( $device === 'Desktop' ) {
		return $browser . '/' . $os;
	}
	return $browser . ' ' . $device . '/' . $os;
}

/**
 * Largest receipt request body accepted, in bytes. A receipt with a full
 * klaro_config snapshot is far smaller; anything bigger is not a receipt.
 */
define( 'KLARO_GEO_RECEIPT_MAX_BYTES', 200000 );

/**
 * Count this request against the receipt rate limit and say whether the limit
 * is exceeded.
 *
 * The endpoint is public, so without a limit a script could fill the receipts
 * table. Requests are counted per anonymized IP (the same /24 or /48 the
 * receipt records) in a transient. A visitor saves consent a handful of times
 * at most; the default of 30 per 5 minutes leaves room for many visitors
 * behind one address.
 *
 * @return bool True when this request is over the limit and must be refused.
 */
function klaro_geo_receipt_rate_limit_exceeded() {
	/**
	 * Filters how many receipts one anonymized IP may submit per window.
	 * Return 0 to disable the limit.
	 *
	 * @param int $limit Receipts per window. Default 30.
	 */
	$limit = (int) apply_filters( 'klaro_geo_receipt_rate_limit', 30 );
	if ( $limit <= 0 ) {
		return false;
	}

	/**
	 * Filters the length of the rate limit window, in seconds.
	 *
	 * @param int $window Default 300 (5 minutes).
	 */
	$window = max( 1, (int) apply_filters( 'klaro_geo_receipt_rate_limit_window', 5 * MINUTE_IN_SECONDS ) );

	$key   = 'klaro_geo_rl_' . md5( klaro_geo_get_anonymized_ip() );
	$count = (int) get_transient( $key );

	if ( $count >= $limit ) {
		return true;
	}

	// The window starts at the first request and is not extended by later ones.
	if ( 0 === $count ) {
		set_transient( $key, 1, $window );
	} else {
		$timeout = (int) get_option( '_transient_timeout_' . $key );
		$left    = $timeout > time() ? $timeout - time() : $window;
		set_transient( $key, $count + 1, $left );
	}

	return false;
}

/**
 * The user a receipt request may be recorded against.
 *
 * A receipt is never refused for its nonce. A nonce printed in a cached page
 * expires (and belongs to whoever the page was generated for), so requiring
 * one loses the record of a real visitor's choice. For anonymous visitors a
 * nonce proves nothing anyway: it is the same for all of them.
 *
 * The nonce still guards one thing: a user ID is recorded only when the
 * request carries that user's valid nonce. Without it another site could have
 * a logged-in user's browser submit a receipt that is then stored under their
 * account. Such a receipt is stored as anonymous instead.
 *
 * @param string $nonce The nonce sent with the request, or ''.
 * @return int User ID, or 0 for anonymous.
 */
function klaro_geo_receipt_user_id( $nonce ) {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return 0;
	}

	if ( ! wp_verify_nonce( $nonce, 'klaro_geo_consent_nonce' ) ) {
		klaro_geo_debug_log( 'Consent receipt from a logged-in user without a valid nonce; storing it as anonymous' );
		return 0;
	}

	return $user_id;
}

/**
 * AJAX handler to store a consent receipt. Public (logged-out visitors submit
 * receipts) and not nonce-protected (see klaro_geo_receipt_user_id()), so the
 * size limit, the rate limit and klaro_geo_sanitize_receipt() are what protect
 * it. Validation and storage are in klaro_geo_store_consent_receipt().
 */
function klaro_geo_ajax_store_consent_receipt() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Deliberate, see klaro_geo_receipt_user_id().
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

	if ( ! isset( $_POST['receipt_data'] ) || ! is_string( $_POST['receipt_data'] ) ) {
		wp_send_json_error( 'No receipt data provided' );
	}

	if ( strlen( $_POST['receipt_data'] ) > KLARO_GEO_RECEIPT_MAX_BYTES ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- length check only.
		wp_send_json_error( 'Receipt data too large', 413 );
	}

	if ( klaro_geo_receipt_rate_limit_exceeded() ) {
		wp_send_json_error( 'Too many requests', 429 );
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string; every field is validated in klaro_geo_sanitize_receipt().
	$receipt_data = json_decode( wp_unslash( $_POST['receipt_data'] ), true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		wp_send_json_error( 'Invalid receipt data' );
	}

	// phpcs:enable WordPress.Security.NonceVerification.Missing

	$result = klaro_geo_store_consent_receipt( $receipt_data, klaro_geo_receipt_user_id( $nonce ) );

	if ( is_wp_error( $result ) ) {
		if ( 'klaro_geo_duplicate_receipt' === $result->get_error_code() ) {
			wp_send_json_success(
				array(
					'receipt_id' => 'duplicate_id_skipped',
					'message'    => $result->get_error_message(),
				)
			);
		}
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success( array( 'receipt_id' => $result ) );
}
add_action( 'wp_ajax_klaro_geo_store_consent_receipt', 'klaro_geo_ajax_store_consent_receipt' );
add_action( 'wp_ajax_nopriv_klaro_geo_store_consent_receipt', 'klaro_geo_ajax_store_consent_receipt' );

// Add admin page for viewing consent receipts
function klaro_geo_add_consent_receipts_page() {
	add_submenu_page(
		'klaro-geo',
		'Consent Receipts',
		'Consent Receipts',
		'manage_options',
		'klaro-geo-consent-receipts',
		'klaro_geo_render_consent_receipts_page'
	);
}
add_action( 'admin_menu', 'klaro_geo_add_consent_receipts_page', 20 );

/**
 * How a stored receipt's GPC signal is shown to an administrator.
 *
 * @param object $receipt A row from the receipts table.
 * @return string
 */
function klaro_geo_receipt_gpc_label( $receipt ) {
	if ( ! isset( $receipt->gpc_signal ) ) {
		return 'Not recorded';
	}
	return $receipt->gpc_signal ? 'Yes' : 'No';
}

/**
 * What caused a stored receipt to be written, for an administrator.
 *
 * @param object $receipt A row from the receipts table.
 * @return string
 */
function klaro_geo_receipt_type_label( $receipt ) {
	if ( isset( $receipt->receipt_type ) && 'gpc' === $receipt->receipt_type ) {
		return 'GPC signal, no choice made';
	}
	return 'Visitor saved a choice';
}

// Render the consent receipts admin page
function klaro_geo_render_consent_receipts_page() {
	// Check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Get pagination parameters
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination on admin page protected by manage_options capability.
	$page     = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
	$per_page = 20;
	$offset   = ( $page - 1 ) * $per_page;

	// Get total count
	$total_items = klaro_geo_receipts_count();
	$total_pages = ceil( $total_items / $per_page );

	// Get receipts for current page
	$receipts = klaro_geo_receipts_get_page( $per_page, $offset );

	// Log query details
	klaro_geo_debug_log( 'Consent Receipts Total Items: ' . $total_items );
	klaro_geo_debug_log( 'Consent Receipts Per Page: ' . $per_page );
	klaro_geo_debug_log( 'Consent Receipts Offset: ' . $offset );
	klaro_geo_debug_log( 'Consent Receipts Page: ' . $page );
	klaro_geo_debug_log( 'Consent Receipts Total Pages: ' . $total_pages );
	klaro_geo_debug_log( 'Consent Receipts Results: ' . wp_json_encode( $receipts ) )

	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<?php if ( empty( $receipts ) ) : ?>
			<p>No consent receipts have been recorded yet.</p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Receipt ID</th>
						<th>Date & Time</th>
						<th>Template</th>
						<th>Source</th>
						<th>Country</th>
						<th>Region</th>
						<th>Recorded when</th>
						<th>GPC</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $receipts as $receipt ) : ?>
						<tr>
							<td><?php echo esc_html( $receipt->receipt_id ); ?></td>
							<td><?php echo esc_html( $receipt->timestamp ); ?></td>
							<td><?php echo esc_html( $receipt->template_name ); ?></td>
							<td><?php echo esc_html( $receipt->template_source ); ?></td>
							<td><?php echo esc_html( $receipt->country_code ); ?></td>
							<td><?php echo esc_html( $receipt->region_code ); ?></td>
							<td><?php echo esc_html( klaro_geo_receipt_type_label( $receipt ) ); ?></td>
							<td><?php echo esc_html( klaro_geo_receipt_gpc_label( $receipt ) ); ?></td>
							<td>
								<button type="button" class="button view-receipt"
										data-receipt-id="<?php echo esc_attr( $receipt->id ); ?>">
									View Details
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php
							/* translators: %s: number of receipts */
							echo esc_html( sprintf( _n( '%s item', '%s items', $total_items, 'klaro-geo' ), number_format_i18n( $total_items ) ) );
							?>
						</span>
						<span class="pagination-links">
							<?php
							echo wp_kses_post(
								paginate_links(
									array(
										'base'      => add_query_arg( 'paged', '%#%' ),
										'format'    => '',
										'prev_text' => '&laquo;',
										'next_text' => '&raquo;',
										'total'     => $total_pages,
										'current'   => $page,
									)
								)
							);
							?>
						</span>
					</div>
				</div>
			<?php endif; ?>

			<!-- Modal for viewing receipt details -->
			<div id="receipt-modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%;
											overflow:auto; background-color:rgba(0,0,0,0.4);">
				<div style="background-color:#fefefe; margin:10% auto; padding:20px; border:1px solid #888; width:80%; max-width:800px;">
					<span id="close-modal" style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
					<h2>Consent Receipt Details</h2>
					<div id="receipt-details"></div>
				</div>
			</div>

			<?php
			// Enqueue receipt viewer behavior via wp_add_inline_script
			$receipt_nonce = esc_js( wp_create_nonce( 'klaro_geo_admin_nonce' ) );
			$receipt_js    = "jQuery(document).ready(function($) {\n";
			$receipt_js   .= "  $('.view-receipt').on('click', function() {\n";
			$receipt_js   .= "    var receiptId = $(this).data('receipt-id');\n";
			$receipt_js   .= "    $.ajax({\n";
			$receipt_js   .= "      url: ajaxurl,\n";
			$receipt_js   .= "      type: 'POST',\n";
			$receipt_js   .= "      data: { action: 'klaro_geo_get_receipt_details', receipt_id: receiptId, nonce: '" . $receipt_nonce . "' },\n";
			$receipt_js   .= "      success: function(response) {\n";
			$receipt_js   .= "        if (response.success) {\n";
			$receipt_js   .= "          var receipt = response.data;\n";
			// Every value is inserted as text. Receipts are submitted by anonymous
			// visitors, so nothing from one may ever be parsed as HTML here.
			$receipt_js   .= "          var pretty = function(json) {\n";
			$receipt_js   .= "            try { return JSON.stringify(JSON.parse(json), null, 2); } catch (e) { return String(json); }\n";
			$receipt_js   .= "          };\n";
			$receipt_js   .= "          var table = $('<table class=\"wp-list-table widefat fixed\"></table>');\n";
			$receipt_js   .= "          var addRow = function(label, value, preformatted) {\n";
			$receipt_js   .= "            var cell = $('<td></td>');\n";
			$receipt_js   .= "            if (preformatted) { cell.append($('<pre></pre>').text(value)); } else { cell.text(value); }\n";
			$receipt_js   .= "            table.append($('<tr></tr>').append($('<th></th>').text(label)).append(cell));\n";
			$receipt_js   .= "          };\n";
			$receipt_js   .= "          addRow('Receipt ID:', receipt.receipt_id);\n";
			$receipt_js   .= "          addRow('Date & Time:', receipt.timestamp);\n";
			$receipt_js   .= "          addRow('Template:', receipt.template_name);\n";
			$receipt_js   .= "          addRow('Source:', receipt.template_source);\n";
			$receipt_js   .= "          addRow('Country:', receipt.country_code || 'N/A');\n";
			$receipt_js   .= "          addRow('Region:', receipt.region_code || 'N/A');\n";
			$receipt_js   .= "          addRow('Recorded when:', receipt.type_label);\n";
			$receipt_js   .= "          addRow('GPC signal:', receipt.gpc_label);\n";
			$receipt_js   .= "          if (receipt.gpc_affected_services) {\n";
			$receipt_js   .= "            addRow('Switched off by GPC:', pretty(receipt.gpc_affected_services), true);\n";
			$receipt_js   .= "          }\n";
			$receipt_js   .= "          addRow('Consent Choices:', pretty(receipt.consent_data), true);\n";
			$receipt_js   .= "          if (receipt.klaro_config) {\n";
			$receipt_js   .= "            addRow('Klaro Config:', pretty(receipt.klaro_config), true);\n";
			$receipt_js   .= "          }\n";
			$receipt_js   .= "          $('#receipt-details').empty().append(table);\n";
			$receipt_js   .= "          $('#receipt-modal').show();\n";
			$receipt_js   .= "        }\n";
			$receipt_js   .= "      }\n";
			$receipt_js   .= "    });\n";
			$receipt_js   .= "  });\n";
			$receipt_js   .= "  $('#close-modal').on('click', function() { $('#receipt-modal').hide(); });\n";
			$receipt_js   .= "  $(window).on('click', function(event) {\n";
			$receipt_js   .= "    if ($(event.target).is('#receipt-modal')) { $('#receipt-modal').hide(); }\n";
			$receipt_js   .= "  });\n";
			$receipt_js   .= "});\n";
			wp_add_inline_script( 'klaro-geo-admin-js', $receipt_js );
			?>
		<?php endif; ?>
	</div>
	<?php
}

// AJAX handler to get receipt details
function klaro_geo_ajax_get_receipt_details() {
	// Verify nonce for security
	$admin_nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $admin_nonce, 'klaro_geo_admin_nonce' ) ) {
		wp_send_json_error( 'Invalid security token' );
	}

	// Check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Insufficient permissions' );
	}

	$receipt_id = isset( $_POST['receipt_id'] ) ? intval( $_POST['receipt_id'] ) : 0;

	if ( ! $receipt_id ) {
		wp_send_json_error( 'Invalid receipt ID' );
	}

	$receipt = klaro_geo_receipts_get_by_id( $receipt_id );

	if ( $receipt ) {
		$receipt->gpc_label  = klaro_geo_receipt_gpc_label( $receipt );
		$receipt->type_label = klaro_geo_receipt_type_label( $receipt );
		wp_send_json_success( $receipt );
	} else {
		wp_send_json_error( 'Receipt not found' );
	}
}
add_action( 'wp_ajax_klaro_geo_get_receipt_details', 'klaro_geo_ajax_get_receipt_details' );
