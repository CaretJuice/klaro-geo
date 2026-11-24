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
 * Get the name of the table that holds each distinct Klaro config receipts
 * refer to (see klaro_geo_consent_config_version()).
 */
function klaro_geo_consent_configs_table_name() {
	global $wpdb;
	return $wpdb->prefix . 'klaro_geo_consent_configs';
}

/**
 * Check if one of the plugin's tables exists.
 *
 * @param string $table_name From klaro_geo_receipts_table_name() or
 *                           klaro_geo_consent_configs_table_name().
 * @phpstan-impure The answer changes when the table is created or dropped.
 */
function klaro_geo_db_table_exists( $table_name ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table existence check; table name from $wpdb->prefix is trusted.
	return $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
}

/**
 * Check if the consent receipts table exists.
 *
 * @phpstan-impure The answer changes when the table is created or dropped.
 */
function klaro_geo_receipts_table_exists() {
	return klaro_geo_db_table_exists( klaro_geo_receipts_table_name() );
}

/**
 * Check if the consent configs table exists.
 *
 * @phpstan-impure The answer changes when the table is created or dropped.
 */
function klaro_geo_consent_configs_table_exists() {
	return klaro_geo_db_table_exists( klaro_geo_consent_configs_table_name() );
}

/**
 * Check if a column exists in the consent receipts table.
 */
function klaro_geo_receipts_column_exists( $column ) {
	global $wpdb;
	$table_name = klaro_geo_receipts_table_name();
	$column     = sanitize_key( $column );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Schema check; table name from $wpdb->prefix is trusted, column name sanitized.
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
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Count query on custom table; table name from $wpdb->prefix is trusted.
	return $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
}

/**
 * Get paginated consent receipts.
 */
function klaro_geo_receipts_get_page( $per_page, $offset ) {
	global $wpdb;
	$table_name = klaro_geo_receipts_table_name();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Paginated query on custom table; not suitable for object caching.
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
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Single row lookup on custom table.
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
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Duplicate check before insert.
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
define( 'KLARO_GEO_RECEIPTS_DB_VERSION', '1.3' );

/**
 * Create the receipts table and the consent configs table, or bring existing
 * ones up to the current schema. Runs on activation, when the stored schema version is behind (see
 * klaro_geo_upgrade_consent_receipts_table()) and when an insert fails.
 *
 * Columns added after 1.0 are listed in $added. Receipts stored before a
 * column existed keep NULL in it: for gpc_signal that means "not recorded",
 * which is not the same as "no signal". Receipts stored before 1.3 hold their
 * own copy of the Klaro config in klaro_config and have no config_hash; later
 * ones refer to a row of the consent configs table instead.
 *
 * @return bool Whether both tables exist with the current schema.
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
		'config_hash'           => 'char(64) DEFAULT NULL',         // 1.3
		'config_status'         => 'varchar(20) DEFAULT NULL',      // 1.3
	);

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	if ( ! klaro_geo_receipts_table_exists() ) {
		$added_sql = '';
		foreach ( $added as $column => $definition ) {
			$added_sql .= "$column $definition,\n";
		}

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
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DDL statement; table name from $wpdb->prefix and the column definitions above are trusted and cannot be parameterized.
			if ( false === $wpdb->query( "ALTER TABLE $table_name ADD COLUMN $column $definition" ) ) {
				klaro_geo_debug_log( "Could not add column $column to $table_name: " . $wpdb->last_error );
				return false; // The version is left as it was, so this is tried again.
			}
		}
	}

	if ( ! klaro_geo_consent_configs_table_exists() ) {
		$configs_table = klaro_geo_consent_configs_table_name();
		dbDelta(
			"CREATE TABLE $configs_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			config_hash char(64) NOT NULL,
			template_name varchar(100) NOT NULL,
			config longtext NOT NULL,
			first_seen datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY config_hash (config_hash)
			) " . $wpdb->get_charset_collate()
		);

		if ( ! klaro_geo_consent_configs_table_exists() ) {
			klaro_geo_debug_log( "Could not create $configs_table: " . $wpdb->last_error );
			return false;
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

// Drop the consent receipts table and the configs its receipts refer to, and
// forget the schema version.
function klaro_geo_drop_consent_receipts_table() {
	global $wpdb;

	foreach ( array( klaro_geo_receipts_table_name(), klaro_geo_consent_configs_table_name() ) as $table_name ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- DDL statement; table name from $wpdb->prefix is trusted and cannot be parameterized.
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
	}
	delete_option( 'klaro_geo_consent_receipts_db_version' );
	delete_option( 'klaro_geo_consent_config_hashes' );
}

/**
 * The version of the Klaro config a template gives visitors: its JSON and a
 * hash that names it.
 *
 * The config holds what a visitor was asked: the consent text in every
 * language and the services with their purposes. A receipt needs it to mean
 * anything, but it is the same for every visitor until someone edits the
 * template or the services, so it is stored once per version (the consent
 * configs table) and each receipt refers to it by hash.
 *
 * The page carries the hash (configHash, specs/frontend-data.spec.yaml) and
 * the receipt sends it back. That ties a receipt to the config its page was
 * rendered with, even when the page came from a cache and the template has
 * been edited since.
 *
 * The template key is part of the hash, so a hash issued for one template
 * cannot be passed off as another's.
 *
 * @param array $payload A payload from klaro_geo_build_template_payload().
 * @return array { hash: string (64 hex characters), json: string }
 */
function klaro_geo_consent_config_version( array $payload ) {
	$json = wp_json_encode( $payload['config'] );

	return array(
		'hash' => hash( 'sha256', $payload['template_key'] . "\n" . $json ),
		'json' => $json,
	);
}

/**
 * Store a config version, unless it is stored already.
 *
 * @param string $template_name Template key the config belongs to.
 * @param array  $version       From klaro_geo_consent_config_version().
 * @return bool Whether the version is in the table.
 */
function klaro_geo_store_consent_config( $template_name, array $version ) {
	global $wpdb;
	$table_name = klaro_geo_consent_configs_table_name();

	$insert = function () use ( $wpdb, $table_name, $template_name, $version ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table insert; IGNORE leaves a version that is already stored as it is.
		return $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix is trusted.
				"INSERT IGNORE INTO $table_name (config_hash, template_name, config, first_seen) VALUES (%s, %s, %s, %s)",
				$version['hash'],
				$template_name,
				$version['json'],
				gmdate( 'Y-m-d H:i:s' )
			)
		);
	};

	$inserted = $insert();
	if ( false === $inserted ) {
		klaro_geo_debug_log( 'Consent config insert failed (' . $wpdb->last_error . '); creating or upgrading the tables and retrying' );
		$inserted = klaro_geo_create_consent_receipts_table() ? $insert() : false;
	}

	if ( false === $inserted ) {
		klaro_geo_debug_log( 'Database error when storing consent config ' . $version['hash'] . ': ' . $wpdb->last_error );
		return false;
	}

	return true;
}

/**
 * Get a stored config version by its hash.
 *
 * @param string $hash The config hash.
 * @return object|null Row with config_hash, template_name, config, first_seen.
 */
function klaro_geo_get_consent_config( $hash ) {
	global $wpdb;
	$table_name = klaro_geo_consent_configs_table_name();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Single row lookup on custom table.
	return $wpdb->get_row(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix is trusted.
			"SELECT * FROM $table_name WHERE config_hash = %s",
			$hash
		)
	);
}

/**
 * Store the config version a page is being rendered with, so a receipt from
 * that page can refer to it however long the page sits in a cache.
 *
 * Called for every template a page carries. The hashes already stored are
 * kept in an autoloaded option, so a page view costs no query and the table
 * is written to only when a template's config has changed.
 *
 * @param array $payload A payload from klaro_geo_build_template_payload().
 */
function klaro_geo_remember_consent_config( array $payload ) {
	if ( ! get_option( 'klaro_geo_enable_consent_receipts', false ) ) {
		return;
	}

	$version = klaro_geo_consent_config_version( $payload );
	$known   = (array) get_option( 'klaro_geo_consent_config_hashes', array() );
	$key     = $payload['template_key'];

	if ( isset( $known[ $key ] ) && $known[ $key ] === $version['hash'] ) {
		return;
	}

	if ( klaro_geo_store_consent_config( $key, $version ) ) {
		$known[ $key ] = $version['hash'];
		update_option( 'klaro_geo_consent_config_hashes', $known );
	}
}

// On deactivation the table is dropped only when "Remove all plugin settings when deactivating" is on.
function klaro_geo_drop_consent_receipts_table_on_deactivate() {
	if ( get_option( 'klaro_geo_cleanup_on_deactivate', false ) ) {
		klaro_geo_drop_consent_receipts_table();
	}
}

/**
 * Validate and sanitize a consent receipt submitted by a browser.
 *
 * Everything in $raw is untrusted: the endpoint is public and takes requests
 * without a nonce. Only the shapes below are accepted; nothing is stored as
 * submitted.
 *
 * Two things a browser may send are ignored on purpose, because the server
 * knows them better: the time (a submitted timestamp could back-date a consent
 * record) and the Klaro config itself (the receipt names a version of it by
 * hash, see klaro_geo_resolve_receipt_config()).
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

	$receipt = array(
		'receipt_id'            => $receipt_id,
		'consent_choices'       => $consent_choices,
		'template_name'         => $text( 'template_name', 100 ),
		'template_source'       => $template_source,
		'country_code'          => $text( 'country_code', 10 ),
		'region_code'           => $region_code,
		'admin_override'        => $admin_override,
		// The config version the page was rendered with, or '' when the page
		// did not say (a copy of klaro-geo.js cached from before it did).
		'config_hash'           => isset( $raw['config_hash'] ) && is_string( $raw['config_hash'] ) && preg_match( '/^[a-f0-9]{64}$/', $raw['config_hash'] ) ? $raw['config_hash'] : '',
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
	if ( isset( $template['plugin_settings']['enable_consent_logging'] ) ) {
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

	// The template must be one that exists: the receipt refers to its config,
	// and a made-up name would otherwise be recorded as if a visitor
	// had been shown it.
	$templates = Klaro_Geo_Template_Settings::get_instance()->get();
	if ( ! isset( $templates[ $receipt['template_name'] ] ) ) {
		klaro_geo_debug_log( 'Consent receipt ' . $receipt['receipt_id'] . ' refused: unknown template "' . $receipt['template_name'] . '"' );
		return new WP_Error( 'klaro_geo_unknown_template', 'Unknown template' );
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
		'timestamp'       => gmdate( 'Y-m-d H:i:s' ),
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

	$config                = klaro_geo_resolve_receipt_config( $receipt, $templates );
	$data['config_hash']   = $config['hash'];
	$data['config_status'] = $config['status'];

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

	return (int) $wpdb->insert_id;
}

/**
 * Decide which config version a receipt refers to, and whether the browser's
 * account of it checks out.
 *
 * The config is never taken from the request; only a hash is, and only a hash
 * this site issued for the same template is accepted:
 *
 * - 'matched': the hash names a version this site served for the template,
 *   the current one or an earlier one (a cached page). The receipt refers to it.
 * - 'mismatch': the hash names nothing this site served for the template. No
 *   page of this site produced such a receipt, so it was altered or made up.
 *   It is stored, marked, and refers to the current version.
 * - 'not-sent': no hash came with the receipt. It refers to the current version.
 *
 * The config is the one before Global Privacy Control is applied in the
 * browser; gpc_affected_services records that part.
 *
 * @param array $receipt   A receipt from klaro_geo_sanitize_receipt(), for a template that exists.
 * @param array $templates All templates.
 * @return array { hash: string, status: 'matched'|'mismatch'|'not-sent' }
 */
function klaro_geo_resolve_receipt_config( array $receipt, array $templates ) {
	$template_name = $receipt['template_name'];
	$submitted     = $receipt['config_hash'];

	if ( '' !== $submitted ) {
		$stored = klaro_geo_get_consent_config( $submitted );
		if ( $stored && $stored->template_name === $template_name ) {
			return array(
				'hash'   => $submitted,
				'status' => 'matched',
			);
		}
	}

	$current = klaro_geo_consent_config_version(
		klaro_geo_build_template_payload( $template_name, $templates, Klaro_Geo_Service_Settings::get_instance()->get() )
	);
	klaro_geo_store_consent_config( $template_name, $current );

	if ( '' === $submitted ) {
		$status = 'not-sent';
	} elseif ( $submitted === $current['hash'] ) {
		$status = 'matched';
	} else {
		$status = 'mismatch';
		klaro_geo_debug_log( 'Consent receipt ' . $receipt['receipt_id'] . ' reports config ' . $submitted . ', which this site did not serve for template "' . $template_name . '"; marked as a mismatch' );
	}

	return array(
		'hash'   => $current['hash'],
		'status' => $status,
	);
}

/**
 * Whether the current request carries the Global Privacy Control header.
 *
 * @return bool
 */
function klaro_geo_request_has_gpc_header() {
	return isset( $_SERVER['HTTP_SEC_GPC'] ) && '1' === trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_GPC'] ) ) );
}

/**
 * Anonymize an IP address: an IPv4 address keeps its first three octets (/24),
 * an IPv6 address its first 48 bits.
 *
 * The address is masked as bytes, not as text. IPv6 has many spellings
 * ("2001:db8::1", "2001:db8:0:0:0:0:0:1") and a pattern that expects one of
 * them leaves the others whole. An IPv4 address written as IPv6
 * ("::ffff:203.0.113.7") is treated as the IPv4 address it is.
 *
 * @param string $ip The address.
 * @return string The anonymized address, or '' when $ip is not an IP address.
 */
function klaro_geo_anonymize_ip( $ip ) {
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return '';
	}

	$packed = inet_pton( $ip );

	if ( 16 === strlen( $packed ) && 0 === strpos( $packed, str_repeat( "\0", 10 ) . "\xff\xff" ) ) {
		$packed = substr( $packed, 12 );
	}

	if ( 4 === strlen( $packed ) ) {
		return inet_ntop( substr( $packed, 0, 3 ) . "\0" );
	}

	return inet_ntop( substr( $packed, 0, 6 ) . str_repeat( "\0", 10 ) );
}

/**
 * The anonymized IP address of the current request. It is stored with a
 * receipt and is what the receipt rate limit counts by.
 *
 * @return string
 */
function klaro_geo_get_anonymized_ip() {
	$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

	/**
	 * Filters the visitor's IP address before it is anonymized.
	 *
	 * REMOTE_ADDR is the address that connected to the web server. Behind a
	 * reverse proxy or CDN that is the proxy, so every visitor shares one
	 * address and one rate limit. A site in that position, whose server does
	 * not already restore the visitor's address, returns it here from the
	 * header its proxy sets. Only trust a header the proxy overwrites: one a
	 * visitor can send lets them pick their own rate limit bucket.
	 *
	 * @param string $ip REMOTE_ADDR.
	 */
	$ip = apply_filters( 'klaro_geo_client_ip', $ip );

	$anonymized = klaro_geo_anonymize_ip( is_string( $ip ) ? trim( $ip ) : '' );
	if ( '' === $anonymized ) {
		klaro_geo_log_once( 'client_ip_invalid', 'WARNING: The request has no valid IP address; the receipt is stored without one and shares a rate limit with other such requests' );
	}

	return $anonymized;
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
 * Largest receipt request body accepted, in bytes. A receipt is far smaller,
 * even one sent by an older cached copy of klaro-geo.js, which included a copy
 * of the Klaro config; anything bigger is not a receipt.
 */
define( 'KLARO_GEO_RECEIPT_MAX_BYTES', 200000 );

/**
 * Count this request against the receipt rate limit and say whether the limit
 * is exceeded.
 *
 * The endpoint is public, so without a limit a script could fill the receipts
 * table. Requests are counted per anonymized IP (the same /24 or /48 the
 * receipt records, see klaro_geo_get_anonymized_ip() for sites behind a
 * proxy) in a transient. A visitor saves consent a handful of times
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

/**
 * What the check of a stored receipt's config found, for an administrator
 * (see klaro_geo_resolve_receipt_config()).
 *
 * @param object $receipt A row from the receipts table.
 * @return string
 */
function klaro_geo_receipt_config_status_label( $receipt ) {
	$labels = array(
		'matched'  => 'Verified: the browser reported the config this site served',
		'mismatch' => 'MISMATCH: the browser reported a config this site never served for this template. The config shown is the one in force when the receipt arrived',
		'not-sent' => 'Not reported by the browser. The config shown is the one in force when the receipt arrived',
	);

	if ( isset( $receipt->config_status, $labels[ $receipt->config_status ] ) ) {
		return $labels[ $receipt->config_status ];
	}
	return 'Not recorded (the receipt holds its own copy of the config)';
}

/**
 * A stored receipt as the admin page shows it: the row, the labels, and the
 * Klaro config it refers to in place of the hash.
 *
 * @param object $receipt A row from the receipts table.
 * @return object
 */
function klaro_geo_receipt_for_display( $receipt ) {
	$receipt->gpc_label    = klaro_geo_receipt_gpc_label( $receipt );
	$receipt->type_label   = klaro_geo_receipt_type_label( $receipt );
	$receipt->config_label = klaro_geo_receipt_config_status_label( $receipt );

	if ( ! empty( $receipt->config_hash ) ) {
		$config = klaro_geo_get_consent_config( $receipt->config_hash );
		if ( $config ) {
			$receipt->klaro_config = $config->config;
		} else {
			klaro_geo_debug_log( 'WARNING: Receipt ' . $receipt->receipt_id . ' refers to config ' . $receipt->config_hash . ', which is not in the consent configs table' );
		}
	}

	return $receipt;
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
	$total_pages = (int) ceil( $total_items / $per_page );

	// Get receipts for current page
	$receipts = klaro_geo_receipts_get_page( $per_page, $offset );

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
			$receipt_js .= "          var pretty = function(json) {\n";
			$receipt_js .= "            try { return JSON.stringify(JSON.parse(json), null, 2); } catch (e) { return String(json); }\n";
			$receipt_js .= "          };\n";
			$receipt_js .= "          var table = $('<table class=\"wp-list-table widefat fixed\"></table>');\n";
			$receipt_js .= "          var addRow = function(label, value, preformatted) {\n";
			$receipt_js .= "            var cell = $('<td></td>');\n";
			$receipt_js .= "            if (preformatted) { cell.append($('<pre></pre>').text(value)); } else { cell.text(value); }\n";
			$receipt_js .= "            table.append($('<tr></tr>').append($('<th></th>').text(label)).append(cell));\n";
			$receipt_js .= "          };\n";
			$receipt_js .= "          addRow('Receipt ID:', receipt.receipt_id);\n";
			$receipt_js .= "          addRow('Date & Time:', receipt.timestamp);\n";
			$receipt_js .= "          addRow('Template:', receipt.template_name);\n";
			$receipt_js .= "          addRow('Source:', receipt.template_source);\n";
			$receipt_js .= "          addRow('Country:', receipt.country_code || 'N/A');\n";
			$receipt_js .= "          addRow('Region:', receipt.region_code || 'N/A');\n";
			$receipt_js .= "          addRow('Recorded when:', receipt.type_label);\n";
			$receipt_js .= "          addRow('GPC signal:', receipt.gpc_label);\n";
			$receipt_js .= "          if (receipt.gpc_affected_services) {\n";
			$receipt_js .= "            addRow('Switched off by GPC:', pretty(receipt.gpc_affected_services), true);\n";
			$receipt_js .= "          }\n";
			$receipt_js .= "          addRow('Consent Choices:', pretty(receipt.consent_data), true);\n";
			$receipt_js .= "          addRow('Config check:', receipt.config_label);\n";
			$receipt_js .= "          if (receipt.config_hash) {\n";
			$receipt_js .= "            addRow('Config version:', receipt.config_hash);\n";
			$receipt_js .= "          }\n";
			$receipt_js .= "          if (receipt.klaro_config) {\n";
			$receipt_js .= "            addRow('Klaro Config:', pretty(receipt.klaro_config), true);\n";
			$receipt_js .= "          }\n";
			$receipt_js .= "          $('#receipt-details').empty().append(table);\n";
			$receipt_js .= "          $('#receipt-modal').show();\n";
			$receipt_js .= "        }\n";
			$receipt_js .= "      }\n";
			$receipt_js .= "    });\n";
			$receipt_js .= "  });\n";
			$receipt_js .= "  $('#close-modal').on('click', function() { $('#receipt-modal').hide(); });\n";
			$receipt_js .= "  $(window).on('click', function(event) {\n";
			$receipt_js .= "    if ($(event.target).is('#receipt-modal')) { $('#receipt-modal').hide(); }\n";
			$receipt_js .= "  });\n";
			$receipt_js .= "});\n";
			wp_add_inline_script( 'klaro-geo-admin-js', $receipt_js );
			?>
		<?php endif; ?>
	</div>
	<?php
}

// AJAX handler to get receipt details
function klaro_geo_ajax_get_receipt_details() {
	klaro_geo_verify_admin_ajax( 'klaro_geo_admin_nonce' );
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the nonce and manage_options are checked by klaro_geo_verify_admin_ajax() above.

	$receipt_id = isset( $_POST['receipt_id'] ) ? intval( $_POST['receipt_id'] ) : 0;

	if ( ! $receipt_id ) {
		wp_send_json_error( 'Invalid receipt ID' );
	}

	$receipt = klaro_geo_receipts_get_by_id( $receipt_id );

	if ( $receipt ) {
		wp_send_json_success( klaro_geo_receipt_for_display( $receipt ) );
	} else {
		wp_send_json_error( 'Receipt not found' );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing
}
add_action( 'wp_ajax_klaro_geo_get_receipt_details', 'klaro_geo_ajax_get_receipt_details' );
