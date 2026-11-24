<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build lookup maps for consent mode services.
 *
 * @param array $services The configured services.
 * @return array Array with 'consent_mode_services' (consent_mode_key => service name)
 *               and 'parent_child_map' (parent service name => array of child names).
 */
function klaro_geo_get_consent_mode_service_maps( $services ) {
	$consent_mode_services = array();
	$parent_child_map      = array(); // Maps parent service name to array of child service names
	foreach ( $services as $service ) {
		if ( isset( $service['is_consent_mode_service'] ) && $service['is_consent_mode_service'] === true ) {
			$consent_mode_key = $service['consent_mode_key'] ?? '';
			if ( ! empty( $consent_mode_key ) ) {
				$consent_mode_services[ $consent_mode_key ] = $service['name'];
			}
			// Track parent-child relationships
			if ( isset( $service['parent_service'] ) && ! empty( $service['parent_service'] ) ) {
				$parent = $service['parent_service'];
				if ( ! isset( $parent_child_map[ $parent ] ) ) {
					$parent_child_map[ $parent ] = array();
				}
				$parent_child_map[ $parent ][] = $service['name'];
			}
		}
	}

	return array(
		'consent_mode_services' => $consent_mode_services,
		'parent_child_map'      => $parent_child_map,
	);
}

/**
 * Template config keys whose admin checkbox renders as checked when the
 * stored config does not set them (see klaro-geo-admin-templates.php).
 *
 * Klaro treats a missing key as false, so config generation backfills these
 * to keep the frontend in line with what the admin page shows. Keep this list
 * in sync with the `checked( isset(...) ? ... : true )` calls on that page.
 *
 * @return string[]
 */
function klaro_geo_get_admin_true_by_default_keys() {
	return array(
		'acceptAll',
		'htmlTexts',
		'groupByPurpose',
		'showDescriptionEmptyStore',
		'disablePoweredBy',
	);
}

/**
 * Whether a service is required under a given template.
 *
 * A service's own Required setting wins. When it is "Use Global Setting"
 * (stored as null, or missing), the template's Required setting applies, so
 * one service (typically google-tag-manager) can be required in one template
 * and consent-gated in another.
 *
 * @param array $service           The stored service.
 * @param bool  $template_required The template's Required setting.
 * @return bool
 */
function klaro_geo_resolve_service_required( array $service, $template_required ) {
	if ( ! isset( $service['required'] ) || '' === $service['required'] || 'global' === $service['required'] ) {
		return (bool) $template_required;
	}
	return filter_var( $service['required'], FILTER_VALIDATE_BOOLEAN );
}

/**
 * The consent key a service is known by in the consent state and in Google
 * Tag Manager: a consent mode service uses its Google key (analytics_storage),
 * any other service uses "<name>_consent" (google_analytics_consent).
 *
 * js/klaro-geo-events.js getServiceConsentKey() is the same rule in JavaScript.
 *
 * @param array $service A service.
 * @return string
 */
function klaro_geo_service_consent_key( array $service ) {
	if ( isset( $service['is_consent_mode_service'] ) && true === $service['is_consent_mode_service'] && ! empty( $service['consent_mode_key'] ) ) {
		return $service['consent_mode_key'];
	}
	return str_replace( '-', '_', $service['name'] ) . '_consent';
}

/**
 * A service's consent default under a template, and where it comes from.
 *
 * In order: a required service is granted; a service with its own Default
 * uses it; otherwise the template's Default applies. Used for the consent
 * defaults sent to the page and for the preview on the Templates page, so the
 * preview cannot disagree with what visitors get.
 *
 * @param array $service           A service.
 * @param bool  $template_default  The template's Default setting.
 * @param bool  $template_required The template's Required setting.
 * @return array { key: string, default: 'granted'|'denied', source: 'required'|'service'|'template' }
 */
function klaro_geo_service_consent_default( array $service, $template_default, $template_required ) {
	if ( klaro_geo_resolve_service_required( $service, $template_required ) ) {
		$default = 'granted';
		$source  = 'required';
	} elseif ( isset( $service['default'] ) && '' !== $service['default'] ) {
		$default = filter_var( $service['default'], FILTER_VALIDATE_BOOLEAN ) ? 'granted' : 'denied';
		$source  = 'service';
	} else {
		$default = $template_default ? 'granted' : 'denied';
		$source  = 'template';
	}

	return array(
		'key'     => klaro_geo_service_consent_key( $service ),
		'default' => $default,
		'source'  => $source,
	);
}

/**
 * Give Klaro a title and description for every configured built-in purpose,
 * and a purpose display order.
 *
 * Purpose translations live in each stored template, so a purpose added after
 * a template was saved (such as 'infrastructure') would otherwise reach Klaro
 * without a title. Only missing 'zz' entries for built-in purposes are filled;
 * anything the template sets is left alone. purposeOrder follows the
 * klaro_geo_purposes setting unless the template sets its own.
 *
 * @param array  $klaro_config    The Klaro config being built (modified in place).
 * @param string $template_to_use Template key, for logging.
 */
function klaro_geo_apply_purpose_defaults( array &$klaro_config, $template_to_use ) {
	$purposes = klaro_geo_get_purposes();
	$defaults = klaro_geo_get_default_purpose_translations();

	$filled = array();
	foreach ( $purposes as $purpose ) {
		if ( isset( $defaults[ $purpose ] ) && empty( $klaro_config['translations']['zz']['purposes'][ $purpose ] ) ) {
			$klaro_config['translations']['zz']['purposes'][ $purpose ] = $defaults[ $purpose ];
			$filled[] = $purpose;
		}
	}
	if ( ! empty( $filled ) ) {
		klaro_geo_debug_log(
			'Template "' . $template_to_use . '" has no translation for purpose(s) ' . implode( ', ', $filled ) .
			'; using the built-in defaults. Re-save the template to customize them.'
		);
	}

	if ( empty( $klaro_config['purposeOrder'] ) ) {
		$klaro_config['purposeOrder'] = $purposes;
	}
}

/**
 * Build the full Klaro config payload for a single template.
 *
 * Contains no visitor-specific data: given the same template/services/options,
 * the output is identical for every request. This is the shared builder used by
 * both server-mode generation (once, for the resolved template) and client-mode
 * generation (once per candidate template).
 *
 * @param string $template_key The requested template key.
 * @param array  $templates    All templates, as Klaro_Geo_Template_Settings provides them.
 * @param array  $services     The configured services, as Klaro_Geo_Service_Settings
 *                             provides them (each has a name, purposes and cookies).
 * @return array Payload with keys:
 *               'template_key'           string  Template actually used (after fallbacks).
 *               'template_source_override' string|null 'fallback'|'hardcoded-fallback' when the requested key was unavailable.
 *               'template_config'        array   Raw template configuration used.
 *               'config'                 array   The klaroConfig array (including services).
 *               'consent_defaults'       array   Google Consent Mode default map.
 *               'gtag_settings'          array   ads_data_redaction / url_passthrough ('true'/'false' strings).
 *               'enable_consent_logging' bool
 *               'gpc_enabled'            bool
 *               'gpc_purposes'           array
 *               'template_settings_block' array  Condensed settings for window.klaroConsentData.templateSettings.
 */
function klaro_geo_build_template_payload( $template_key, $templates, $services ) {
	// Get the template config from the database, or fall back to default if not found
	// Try to get the template, tracking which fallback was used
	// IMPORTANT: Update $template_to_use when falling back so that metadata
	// (klaroGeoConsentTemplate, klaroGeoTemplateSource) reflects actual template used
	$template_source_detail      = '';
	$template_source_override    = null;
	$template_to_use             = $template_key;
	$original_template_requested = $template_key;
	if ( isset( $templates[ $template_to_use ] ) ) {
		$template_config        = $templates[ $template_to_use ];
		$template_source_detail = 'exact match from database';
	} elseif ( isset( $templates['default'] ) ) {
		$template_config        = $templates['default'];
		$template_source_detail = 'fallback to "default" template (requested "' . $template_to_use . '" not found)';
		klaro_geo_debug_log( 'WARNING: Requested template "' . $template_to_use . '" not found in database, falling back to "default"' );
		// Update variables so metadata reflects actual template used
		$template_to_use          = 'default';
		$template_source_override = 'fallback';
	} else {
		$template_config        = Klaro_Geo_Template_Settings::normalize_template( 'default', klaro_geo_get_default_templates()['default'] );
		$template_source_detail = 'fallback to hardcoded defaults (neither "' . $original_template_requested . '" nor "default" found in database)';
		klaro_geo_debug_log( 'WARNING: Neither requested template "' . $original_template_requested . '" nor "default" found in database, using hardcoded defaults' );
		// Update variables so metadata reflects actual template used
		$template_to_use          = 'default';
		$template_source_override = 'hardcoded-fallback';
	}

	klaro_geo_debug_log( 'Template lookup: ' . $template_source_detail );

	// Check if consent receipts are enabled
	$enable_consent_receipts = get_option( 'klaro_geo_enable_consent_receipts', false );

	// Initialize custom template settings that are not part of klaroConfig
	$custom_template_settings = array(
		'enableConsentLogging' => $enable_consent_receipts, // Use the global setting by default
	);

	// Get plugin settings from the template
	if ( isset( $template_config['plugin_settings'] ) ) {
		// Get enableConsentLogging setting
		if ( isset( $template_config['plugin_settings']['enable_consent_logging'] ) ) {
			$custom_template_settings['enableConsentLogging'] = $template_config['plugin_settings']['enable_consent_logging'];
		}
	}

	// GPC settings: global + per-template
	$gpc_global_enabled   = get_option( 'klaro_geo_gpc_enabled', true );
	$gpc_template_enabled = isset( $template_config['plugin_settings']['gpc_enabled'] )
		? $template_config['plugin_settings']['gpc_enabled']
		: true;
	$gpc_enabled          = $gpc_global_enabled && $gpc_template_enabled;
	$all_purposes         = klaro_geo_get_purposes();
	$gpc_purposes         = isset( $template_config['plugin_settings']['gpc_purposes'] )
		? $template_config['plugin_settings']['gpc_purposes']
		: $all_purposes;

	klaro_geo_debug_log(
		'GPC: global_enabled=' . ( $gpc_global_enabled ? 'true' : 'false' ) .
		', template_enabled=' . ( $gpc_template_enabled ? 'true' : 'false' ) .
		', effective=' . ( $gpc_enabled ? 'true' : 'false' ) .
		', purposes=' . implode( ',', $gpc_purposes )
	);

	// The template's config is Klaro's config, apart from what is added below.
	// Its shape is settled when templates are loaded
	// (Klaro_Geo_Template_Settings::normalize_template()).
	$klaro_config = $template_config['config'];

	klaro_geo_apply_purpose_defaults( $klaro_config, $template_to_use );

	// Never hand Klaro a global required=true. Klaro's changeAll() grants every
	// service when config.required is set, so Decline accepted everything,
	// including services explicitly marked not required; its UI also kept such
	// services switchable while loading them regardless. The template's Required
	// setting is applied per service instead (klaro_geo_resolve_service_required()).
	if ( ! empty( $klaro_config['required'] ) ) {
		klaro_geo_debug_log( 'Template "' . $template_to_use . '" Required=true applied per service to services set to "Use Global Setting"; global config.required set to false' );
	}
	$klaro_config['required'] = false;

	// Set default cookiePath if not configured
	if ( ! isset( $klaro_config['cookiePath'] ) || empty( $klaro_config['cookiePath'] ) ) {
		$klaro_config['cookiePath'] = '/';
	}

	// Auto-detect cookieDomain with leading dot for subdomain sharing if not explicitly set
	if ( ! isset( $klaro_config['cookieDomain'] ) || $klaro_config['cookieDomain'] === '' ) {
		// Get the site's domain from WordPress
		$raw_site_url = get_site_url();
		$site_url     = wp_parse_url( $raw_site_url, PHP_URL_HOST );

		if ( $site_url ) {
			// Add leading dot for subdomain sharing (e.g., ".example.com")
			$klaro_config['cookieDomain'] = '.' . preg_replace( '/^www\./', '', $site_url );
			klaro_geo_debug_log( 'cookieDomain auto-detected: ' . $klaro_config['cookieDomain'] );
		} else {
			// Fallback: leave cookieDomain empty but log a warning
			klaro_geo_debug_log( 'WARNING: Could not auto-detect cookieDomain' );
			$klaro_config['cookieDomain'] = '';
		}
	} else {
		klaro_geo_debug_log( 'cookieDomain from template: ' . $klaro_config['cookieDomain'] );
	}

	// Build the services configuration
	$klaro_config['services'] = array();

	// NOTE: Consent mode is ALWAYS enabled
	// Consent mode services are now first-class Klaro services with is_consent_mode_service=true
	// The old template-level analytics_storage_service/ad_storage_service mappings are removed

	// Build map of consent mode services for quick lookup
	$service_maps          = klaro_geo_get_consent_mode_service_maps( $services );
	$consent_mode_services = $service_maps['consent_mode_services'];
	$parent_child_map      = $service_maps['parent_child_map'];

	// Get template's default consent state (for inheritance)
	$template_default_consent = isset( $template_config['config']['default'] ) && $template_config['config']['default'] ? true : false;

	// Template's required setting, applied per service to services set to
	// "Use Global Setting" (see klaro_geo_resolve_service_required()).
	$template_required = isset( $template_config['config']['required'] ) &&
		filter_var( $template_config['config']['required'], FILTER_VALIDATE_BOOLEAN );

	// Get gtag settings from template if available (ads_data_redaction, url_passthrough)
	$consent_mode_settings = array();
	if ( isset( $template_config['consent_mode_settings'] ) ) {
		$consent_mode_settings = $template_config['consent_mode_settings'];
	} elseif ( isset( $template_config['config'] ) && isset( $template_config['config']['consent_mode_settings'] ) ) {
		$consent_mode_settings = $template_config['config']['consent_mode_settings'];
	}
	$gtag_settings      = isset( $consent_mode_settings['gtag_settings'] ) ? $consent_mode_settings['gtag_settings'] : array();
	$ads_data_redaction = isset( $gtag_settings['ads_data_redaction'] ) ? $gtag_settings['ads_data_redaction'] : 'true';
	$url_passthrough    = isset( $gtag_settings['url_passthrough'] ) ? $gtag_settings['url_passthrough'] : 'false';

	// Build consent defaults - all start as 'denied' for Basic Consent Mode
	// Standard Google Consent Mode v2 keys default to 'denied'
	$dynamic_consent_defaults = array(
		'ad_storage'         => 'denied',
		'analytics_storage'  => 'denied',
		'ad_user_data'       => 'denied',
		'ad_personalization' => 'denied',
	);

	// Add dynamic service consent keys for ALL services
	// Respects: required services → granted, service override → explicit default, otherwise → template default
	$consent_keys = array();
	foreach ( $services as $service ) {
		$consent_default                                     = klaro_geo_service_consent_default( $service, $template_default_consent, $template_required );
		$dynamic_consent_defaults[ $consent_default['key'] ] = $consent_default['default'];
		$consent_keys[ $service['name'] ]                    = $consent_default['key'];
	}

	// A child consent mode service is denied while its parent is not granted,
	// as in KlaroGeoEvents.buildConsentMode() (specs/frontend-data.spec.yaml).
	foreach ( $parent_child_map as $parent => $children ) {
		$parent_granted = isset( $consent_keys[ $parent ] ) && 'granted' === $dynamic_consent_defaults[ $consent_keys[ $parent ] ];
		if ( ! $parent_granted ) {
			foreach ( $children as $child ) {
				$dynamic_consent_defaults[ $consent_keys[ $child ] ] = 'denied';
			}
		}
	}

	// Process each service
	foreach ( $services as $service ) {
		$service_config = array(
			'name'     => $service['name'],
			'purposes' => $service['purposes'],
			'cookies'  => $service['cookies'],
		);

		// Always emit required per service, resolving "Use Global Setting" against
		// this template, so Klaro never needs its global config.required (see below).
		$service_config['required'] = klaro_geo_resolve_service_required( $service, $template_required );

		// default is only emitted when explicit; Klaro falls back to config.default
		// for the rest, which inherits correctly.
		if ( isset( $service['default'] ) ) {
			$service_config['default'] = filter_var( $service['default'], FILTER_VALIDATE_BOOLEAN );
		}

		// Add optional fields if they exist
		if ( isset( $service['optOut'] ) ) {
			$service_config['optOut'] = filter_var( $service['optOut'], FILTER_VALIDATE_BOOLEAN );
		}

		if ( isset( $service['onlyOnce'] ) ) {
			$service_config['onlyOnce'] = filter_var( $service['onlyOnce'], FILTER_VALIDATE_BOOLEAN );
		}

		if ( isset( $service['contextualConsentOnly'] ) ) {
			$service_config['contextualConsentOnly'] = filter_var( $service['contextualConsentOnly'], FILTER_VALIDATE_BOOLEAN );
		}

		// Add translations if they exist
		if ( isset( $service['translations'] ) && ! empty( $service['translations'] ) ) {
			$service_config['translations'] = $service['translations'];
		}

		// Add consent mode metadata for JavaScript to use
		if ( isset( $service['is_consent_mode_service'] ) && $service['is_consent_mode_service'] === true ) {
			$service_config['is_consent_mode_service'] = true;
			if ( isset( $service['consent_mode_key'] ) ) {
				$service_config['consent_mode_key'] = $service['consent_mode_key'];
			}
			if ( isset( $service['parent_service'] ) ) {
				$service_config['parent_service'] = $service['parent_service'];
			}
		}

		// Compute GPC sensitivity: explicit per-service > template purposes > auto-detect
		if ( $gpc_enabled ) {
			if ( isset( $service['gpc_sensitive'] ) && $service['gpc_sensitive'] === true ) {
				$service_config['gpc_sensitive'] = true;
			} elseif ( isset( $service['gpc_sensitive'] ) && $service['gpc_sensitive'] === false ) {
				$service_config['gpc_sensitive'] = false;
			} else {
				// Auto-detect: check if any service purpose matches gpc_purposes
				$is_sensitive                    = ! empty( array_intersect( $service['purposes'], $gpc_purposes ) );
				$service_config['gpc_sensitive'] = $is_sensitive;
			}
		}

		// Skip hidden services
		if ( isset( $service['hidden'] ) && $service['hidden'] === true ) {
			klaro_geo_debug_log( 'Skipping hidden service: ' . $service['name'] );
			continue;
		}

		$klaro_config['services'][] = $service_config;
	}

	// Store parent-child map for JavaScript to use for dependency enforcement
	$klaro_config['consent_mode_dependencies'] = $parent_child_map;

	// Transform styling settings from object to array format if needed
	if ( isset( $klaro_config['styling'] ) && isset( $klaro_config['styling']['theme'] ) ) {
		if ( is_array( $klaro_config['styling']['theme'] ) ) {
			// Check if theme is in object format (with keys like 'color', 'position', 'width')
			$is_associative = false;
			foreach ( array_keys( $klaro_config['styling']['theme'] ) as $key ) {
				if ( is_string( $key ) ) {
					$is_associative = true;
					break;
				}
			}

			if ( $is_associative ) {
				// Extract values from the object
				$theme_values = array();

				// Add color if it exists
				if ( isset( $klaro_config['styling']['theme']['color'] ) ) {
					$theme_values[] = $klaro_config['styling']['theme']['color'];
				}

				// Add position if it exists
				if ( isset( $klaro_config['styling']['theme']['position'] ) ) {
					$theme_values[] = $klaro_config['styling']['theme']['position'];
				}

				// Add width if it exists
				if ( isset( $klaro_config['styling']['theme']['width'] ) ) {
					$theme_values[] = $klaro_config['styling']['theme']['width'];
				}

				// Replace the object with the array
				$klaro_config['styling']['theme'] = $theme_values;
			}
		}
	}

	// Condensed template settings for window.klaroConsentData.templateSettings
	$template_settings_block = array(
		'consentModalTitle'       => isset( $template_config['config']['translations']['zz']['consentModal']['title'] ) ?
			$template_config['config']['translations']['zz']['consentModal']['title'] : 'Privacy Settings',
		'consentModalDescription' => isset( $template_config['config']['translations']['zz']['consentModal']['description'] ) ?
			$template_config['config']['translations']['zz']['consentModal']['description'] : '',
		'acceptAllText'           => isset( $template_config['config']['translations']['zz']['acceptAll'] ) ?
			$template_config['config']['translations']['zz']['acceptAll'] : 'Accept All',
		'declineAllText'          => isset( $template_config['config']['translations']['zz']['decline'] ) ?
			$template_config['config']['translations']['zz']['decline'] : 'Decline All',
		'defaultConsent'          => isset( $template_config['config']['default'] ) && $template_config['config']['default'] ? true : false,
		'requiredConsent'         => isset( $template_config['config']['required'] ) && $template_config['config']['required'] ? true : false,
	);

	return array(
		'template_key'             => $template_to_use,
		'template_source_override' => $template_source_override,
		'template_config'          => $template_config,
		'config'                   => $klaro_config,
		'consent_defaults'         => $dynamic_consent_defaults,
		'gtag_settings'            => array(
			'ads_data_redaction' => $ads_data_redaction,
			'url_passthrough'    => $url_passthrough,
		),
		'enable_consent_logging'   => (bool) $custom_template_settings['enableConsentLogging'],
		'gpc_enabled'              => (bool) $gpc_enabled,
		'gpc_purposes'             => $gpc_purposes,
		'template_settings_block'  => $template_settings_block,
	);
}

/**
 * Build window.klaroConsentData, the one object the frontend scripts read
 * plugin settings and the resolved template from.
 *
 * Emitted on every page in both geo modes. It is the only definition: nothing
 * else may assign window.klaroConsentData server-side.
 *
 * enableConsentLogging is the effective value: the global consent receipts
 * setting AND the template's own setting. The AJAX endpoint details are only
 * included when receipts are enabled globally.
 *
 * @param array $payload  A payload from klaro_geo_build_template_payload().
 * @param array $services The configured services.
 * @param array $resolved Per-request values: templateName, templateSource,
 *                        detectedCountry, detectedRegion, adminOverride.
 *                        Client mode passes none; the resolver fills them.
 * @return array
 */
function klaro_geo_build_consent_data( array $payload, array $services, array $resolved = array() ) {
	$receipts_enabled = (bool) get_option( 'klaro_geo_enable_consent_receipts', false );
	$service_maps     = klaro_geo_get_consent_mode_service_maps( $services );

	$data = array(
		'gtmId'                  => get_option( 'klaro_geo_gtm_id', '' ),
		'consentModeType'        => get_option( 'klaro_geo_consent_mode_type', 'basic' ),
		'templateName'           => $resolved['templateName'] ?? '',
		'templateSource'         => $resolved['templateSource'] ?? '',
		'detectedCountry'        => $resolved['detectedCountry'] ?? '',
		'detectedRegion'         => $resolved['detectedRegion'] ?? '',
		'adminOverride'          => ! empty( $resolved['adminOverride'] ),
		'consentReceipts'        => $receipts_enabled,
		'enableConsentLogging'   => $receipts_enabled && $payload['enable_consent_logging'],
		// Names the Klaro config this page carries; a receipt sends it back
		// (klaro_geo_consent_config_version()).
		'configHash'             => klaro_geo_consent_config_version( $payload )['hash'],
		'consentMode'            => 'v2',
		'suppressConsentsEvents' => (bool) get_option( 'klaro_geo_suppress_consents_events', true ),
		'consentModeServices'    => (object) $service_maps['consent_mode_services'],
		'parentChildMap'         => (object) $service_maps['parent_child_map'],
		'templateSettings'       => array_merge(
			$payload['template_settings_block'],
			array(
				'config' => array(
					'consent_mode_settings' => array(
						'consent_defaults' => $payload['consent_defaults'],
						'gtag_settings'    => array(
							'ads_data_redaction' => $payload['gtag_settings']['ads_data_redaction'] === 'true',
							'url_passthrough'    => $payload['gtag_settings']['url_passthrough'] === 'true',
						),
					),
				),
			)
		),
	);

	if ( $receipts_enabled ) {
		$data['ajaxUrl'] = admin_url( 'admin-ajax.php' );

		// Only a logged-in user's receipt uses a nonce (to record their user ID,
		// see klaro_geo_receipt_user_id()). Anonymous pages carry none, so there
		// is nothing in them to expire in a page cache.
		if ( is_user_logged_in() ) {
			$data['nonce'] = wp_create_nonce( 'klaro_geo_consent_nonce' );
		}
	}

	return $data;
}

/**
 * Whether client-side geo resolution mode is active for this request.
 *
 * Client mode is skipped for admin debug overrides: logged-in requests bypass
 * page caches, so server-side resolution is both safe and required for the
 * debug workflow.
 *
 * @param bool $is_admin_override Whether the admin debug geo override is active.
 * @return bool
 */
function klaro_geo_is_client_geo_active( $is_admin_override = false ) {
	if ( $is_admin_override ) {
		return false;
	}
	return get_option( 'klaro_geo_geo_resolution_mode', 'server' ) === 'client';
}

/**
 * One template as the frontend receives it: an entry of the payload's
 * 'templates' map.
 *
 * @param array $payload A payload from klaro_geo_build_template_payload().
 * @return array
 */
function klaro_geo_frontend_template_entry( array $payload ) {
	return array(
		'config'               => $payload['config'],
		'consentDefaults'      => $payload['consent_defaults'],
		'gtagSettings'         => $payload['gtag_settings'],
		'enableConsentLogging' => $payload['enable_consent_logging'],
		'gpcEnabled'           => $payload['gpc_enabled'],
		'templateSettings'     => $payload['template_settings_block'],
		'configHash'           => klaro_geo_consent_config_version( $payload )['hash'],
	);
}

/**
 * Render the inline script that hands the frontend its data, in both geo modes.
 *
 * It contains data only: window.klaroGeoTemplatePayload (the template payload),
 * the dataLayer/gtag bootstrap and window.klaroConsentData. All behaviour
 * (GPC, klaroConfigLoaded, initializing Klaro) lives in
 * js/klaro-geo-bootstrap.js, which this script is attached in front of.
 *
 * Slashes are left escaped by wp_json_encode() on purpose: a stored value
 * containing "</script>" cannot end the inline script.
 *
 * @param array $frontend_payload The window.klaroGeoTemplatePayload payload.
 * @param array $consent_data     The window.klaroConsentData object.
 * @return string
 */
function klaro_geo_render_frontend_payload( array $frontend_payload, array $consent_data ) {
	$content  = "// Klaro Geo frontend payload (applied by klaro-geo-bootstrap.js)\n";
	$content .= 'window.klaroGeoTemplatePayload = ' . wp_json_encode( $frontend_payload ) . ";\n\n";
	$content .= "window.dataLayer = window.dataLayer || [];\n";
	$content .= "window.gtag = window.gtag || function(){dataLayer.push(arguments);};\n";
	$content .= "window.klaroGeo = window.klaroGeo || {};\n\n";
	$content .= "// Plugin settings and resolved template for the frontend scripts\n";
	$content .= 'window.klaroConsentData = ' . wp_json_encode( $consent_data ) . ";\n";

	return $content;
}

/**
 * The inline script that hands the frontend its data for the current request
 * (the template payload and klaroConsentData). Nothing is written to disk.
 *
 * @return string JavaScript content for wp_add_inline_script.
 */
function klaro_geo_frontend_inline_script() {
	$frontend = klaro_geo_get_frontend_payload();
	return klaro_geo_render_frontend_payload( $frontend['payload'], $frontend['consent_data'] );
}

/**
 * Build the data the frontend receives for the current request.
 *
 * Server mode resolves the visitor's template here and ships that one
 * template, marked as already resolved. Client mode ships the rules and every
 * candidate template and lets the browser resolve (cache-safe). Either way the
 * same script applies the result.
 *
 * Also stores the consent defaults for the <head> in
 * $GLOBALS['klaro_geo_consent_defaults'] / ['klaro_geo_gtag_settings'].
 *
 * @return array {
 *     @type array $payload      window.klaroGeoTemplatePayload.
 *     @type array $consent_data window.klaroConsentData.
 * }
 */
function klaro_geo_get_frontend_payload() {
	// Get the services using the service settings class
	$service_settings = Klaro_Geo_Service_Settings::get_instance();
	$services         = $service_settings->get();

	// Get user location
	$location        = klaro_geo_get_user_location();
	$user_country    = $location['country'];
	$user_region     = $location['region'];
	$using_debug_geo = $location['is_admin_override'];

	// Client mode: emit a cache-safe payload with no visitor-specific values.
	// The browser resolves geo and selects the template (js/klaro-geo-bootstrap.js).
	if ( klaro_geo_is_client_geo_active( $using_debug_geo ) ) {
		return klaro_geo_get_client_frontend_payload( $services );
	}

	klaro_geo_debug_log(
		'User location - Country: ' . $user_country . ', Region: ' . $user_region .
						( $using_debug_geo ? ' (admin override)' : '' )
	);

	// Get effective settings for the location, passing the admin override flag
	$effective_settings = klaro_geo_get_effective_settings(
		$user_country . ( $user_region ? '-' . $user_region : '' ),
		$using_debug_geo
	);

	// Default template source to fallback for dataLayer
	$template_source = 'fallback';

	// Override template source by detected template
	if ( isset( $effective_settings['source'] ) ) {
		$template_source = $effective_settings['source'];
	}

	klaro_geo_debug_log( 'Template source: ' . $template_source . ( $using_debug_geo ? ' (admin debug override)' : '' ) );

	// Extract template from effective settings
	$template_to_use = $effective_settings['template'] ?? 'default';

	klaro_geo_debug_log( 'Using template: ' . $template_to_use );

	// Get template configuration from the database
	$template_settings = Klaro_Geo_Template_Settings::get_instance();
	$templates         = $template_settings->get();
	klaro_geo_debug_log( 'Templates available: ' . implode( ', ', array_keys( $templates ) ) );

	// Build the full payload for the resolved template
	$payload = klaro_geo_build_template_payload( $template_to_use, $templates, $services );
	klaro_geo_remember_consent_config( $payload );

	// Update metadata when the builder had to fall back
	$template_to_use = $payload['template_key'];
	if ( $payload['template_source_override'] !== null ) {
		$template_source = $payload['template_source_override'];
	}

	// Store consent defaults in globals for klaro_geo_enqueue_scripts() to output
	$GLOBALS['klaro_geo_consent_defaults'] = $payload['consent_defaults'];
	$GLOBALS['klaro_geo_gtag_settings']    = $payload['gtag_settings'];

	// One template, already resolved. The browser applies it without a lookup.
	$frontend_payload = array(
		'mode'            => 'server',
		'resolved'        => array(
			'template'       => $template_to_use,
			'templateSource' => $template_source,
			'country'        => $user_country,
			'region'         => $user_region,
			'geoSource'      => $using_debug_geo ? 'debug' : 'server',
			'adminOverride'  => (bool) $using_debug_geo,
		),
		'rules'           => array(
			'defaultTemplate' => $template_to_use,
		),
		'templates'       => array(
			$template_to_use => klaro_geo_frontend_template_entry( $payload ),
		),
		'consentReceipts' => (bool) get_option( 'klaro_geo_enable_consent_receipts', false ),
	);

	$consent_data = klaro_geo_build_consent_data(
		$payload,
		$services,
		array(
			'templateName'    => $template_to_use,
			'templateSource'  => $template_source,
			'detectedCountry' => $user_country,
			'detectedRegion'  => $user_region,
			'adminOverride'   => $using_debug_geo,
		)
	);

	return array(
		'payload'      => $frontend_payload,
		'consent_data' => $consent_data,
	);
}

/**
 * Build the cache-safe client-mode frontend payload.
 *
 * The result MUST be identical for every anonymous visitor: it contains the
 * location→template rules map plus a full config payload for each candidate
 * template, and NO detected geo or pre-selected template. Resolution happens
 * in js/klaro-geo-bootstrap.js.
 *
 * @param array $services The configured services.
 * @return array Same shape as klaro_geo_get_frontend_payload().
 */
function klaro_geo_get_client_frontend_payload( $services ) {
	$template_settings = Klaro_Geo_Template_Settings::get_instance();
	$templates         = $template_settings->get();

	$country_settings_class = Klaro_Geo_Country_Settings::get_instance();
	$geo_settings           = $country_settings_class->get();

	$default_template = $country_settings_class->get_default_template();
	if ( empty( $default_template ) ) {
		$default_template = 'default';
	}

	$visible_countries = $country_settings_class->load_visible_countries();

	// Build the rules map and collect candidate template keys
	$rules_countries = array();
	$candidate_keys  = array( $default_template );

	foreach ( $geo_settings as $key => $value ) {
		// Country entries are 2-letter uppercase codes; skip meta keys like default_template
		if ( ! is_string( $key ) || ! preg_match( '/^[A-Z]{2}$/', $key ) || ! is_array( $value ) ) {
			continue;
		}

		// Empty-string values are kept: PHP treats them as set, which routes
		// through the validation fallback — the JS resolver must see the same input.
		$country_entry = array();
		if ( isset( $value['template'] ) ) {
			$country_entry['template'] = $value['template'];
			if ( $value['template'] !== 'inherit' && $value['template'] !== '' ) {
				$candidate_keys[] = $value['template'];
			}
		}

		if ( isset( $value['regions'] ) ) {
			$regions = array();
			foreach ( $value['regions'] as $region_code => $region_template ) {
				$regions[ $region_code ] = $region_template;
				if ( $region_template !== 'inherit' && $region_template !== '' ) {
					$candidate_keys[] = $region_template;
				}
			}
			$country_entry['regions'] = (object) $regions;
		}

		$rules_countries[ $key ] = (object) $country_entry;
	}

	// The 'default' template is the terminal fallback of the builder; always include it if present
	if ( isset( $templates['default'] ) ) {
		$candidate_keys[] = 'default';
	}
	$candidate_keys = array_values( array_unique( $candidate_keys ) );

	// Build a payload per candidate template. Keys missing from the database are
	// skipped (the client resolver falls back to the default template), EXCEPT the
	// default template itself, which uses the builder's own fallback chain so a
	// usable config always ships.
	$payload_templates = array();
	$default_payload   = null;
	foreach ( $candidate_keys as $candidate_key ) {
		if ( $candidate_key !== $default_template && ! isset( $templates[ $candidate_key ] ) ) {
			klaro_geo_debug_log( 'Client mode: skipping missing template "' . $candidate_key . '" (resolver will fall back)' );
			continue;
		}

		$payload = klaro_geo_build_template_payload( $candidate_key, $templates, $services );
		klaro_geo_remember_consent_config( $payload );

		$payload_templates[ $candidate_key ] = klaro_geo_frontend_template_entry( $payload );

		if ( $candidate_key === $default_template ) {
			$default_payload = $payload;
		}
	}

	// Head consent defaults come from the fallback template (cache-safe: same for
	// all visitors). After resolution the client pushes gtag('consent','update')
	// with the selected template's defaults.
	$GLOBALS['klaro_geo_consent_defaults'] = $default_payload['consent_defaults'];
	$GLOBALS['klaro_geo_gtag_settings']    = $default_payload['gtag_settings'];

	$timeout_ms = (int) get_option( 'klaro_geo_client_geo_timeout_ms', 2500 );
	if ( $timeout_ms <= 0 ) {
		$timeout_ms = 2500;
	}

	$client_payload = array(
		'mode'             => 'client',
		'geoAjax'          => array(
			'url'    => admin_url( 'admin-ajax.php' ),
			// geoip-detect's AJAX endpoint action. Verified against geoip-detect at
			// implementation time; a failing/absent endpoint falls through the
			// resolver's fallback chain (timezone → fallback template).
			'action' => 'geoip_detect2_get_info_from_current_ip',
		),
		'timeoutMs'        => $timeout_ms,
		'timezoneFallback' => (bool) get_option( 'klaro_geo_client_geo_timezone_fallback', true ),
		'rules'            => array(
			'defaultTemplate'  => $default_template,
			'visibleCountries' => array_values( $visible_countries ),
			'countries'        => (object) $rules_countries,
		),
		'templates'        => (object) $payload_templates,
		'consentReceipts'  => (bool) get_option( 'klaro_geo_enable_consent_receipts', false ),
	);

	// The klaroConsentData base has empty template/geo fields; the client
	// resolver fills them BEFORE Klaro initializes, so no receipt can be
	// generated with unresolved values.
	return array(
		'payload'      => $client_payload,
		'consent_data' => klaro_geo_build_consent_data( $default_payload, $services ),
	);
}
