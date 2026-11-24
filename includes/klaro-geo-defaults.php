<?php
/**
 * Default values for Klaro Geo
 *
 * This file contains functions that return default values for templates and services.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default purpose keys, in display order.
 *
 * 'infrastructure' holds the Google Tag Manager container. GTM used to sit in
 * both 'analytics' and 'advertising', but Klaro draws a purpose as checked
 * whenever any of its services is on, so a service shared by two purposes
 * made enabling one purpose show the other as on. A visitor's click on that
 * falsely-checked purpose then switched it off instead of on.
 *
 * @return string[]
 */
function klaro_geo_get_default_purposes() {
	return array( 'functional', 'infrastructure', 'analytics', 'advertising' );
}

/**
 * The klaro_geo_purposes option's default value (comma-separated).
 *
 * @return string
 */
function klaro_geo_get_default_purposes_string() {
	return implode( ',', klaro_geo_get_default_purposes() );
}

/**
 * The configured purpose keys (klaro_geo_purposes option), trimmed, empty
 * entries dropped.
 *
 * @return string[]
 */
function klaro_geo_get_purposes() {
	$purposes = array_map( 'trim', explode( ',', (string) get_option( 'klaro_geo_purposes', klaro_geo_get_default_purposes_string() ) ) );
	return array_values( array_diff( $purposes, array( '' ) ) );
}

/**
 * Default Klaro translations (title, description) for the built-in purposes.
 *
 * Used by the hardcoded templates, as the templates admin page's defaults,
 * and by config generation to fill in a built-in purpose that a stored
 * template has no translation for.
 *
 * @return array<string, array{title: string, description: string}>
 */
function klaro_geo_get_default_purpose_translations() {
	return array(
		'functional'     => array(
			'title'       => 'Functional',
			'description' => 'These services are essential for the correct functioning of this website. You cannot disable them as the website would not work properly.',
		),
		'infrastructure' => array(
			'title'       => 'Site Infrastructure',
			'description' => 'These services load and run the other tools on this website, such as a tag manager. They do not track you themselves, but analytics and advertising tools cannot run without them.',
		),
		'analytics'      => array(
			'title'       => 'Analytics',
			'description' => 'These services allow us to analyze website usage to evaluate and improve its performance.',
		),
		'advertising'    => array(
			'title'       => 'Advertising',
			'description' => 'These services process personal information to show you personalized advertisements.',
		),
	);
}

/**
 * The default Klaro translations ("zz" is Klaro's fallback language), shared by
 * every built-in template.
 *
 * @return array
 */
function klaro_geo_get_default_translations() {
	return array(
		'zz' => array(
			'privacyPolicyUrl' => '/privacy-policy/',
			'consentModal'     => array(
				'title'       => 'Privacy Settings',
				'description' => 'Here you can assess and customize the services that we\'d like to use on this website. You\'re in charge! Enable or disable services as you see fit.',
			),
			'consentNotice'    => array(
				'title'             => 'Privacy Settings',
				'changeDescription' => 'There were changes since your last visit, please update your consent.',
				'description'       => 'We use cookies and similar technologies to provide certain features, enhance the user experience and deliver content that is relevant to your interests.',
				'learnMore'         => 'Learn more',
			),
			'acceptAll'        => 'Accept all',
			'acceptSelected'   => 'Accept selected',
			'decline'          => 'I decline',
			'close'            => 'Close',
			'purposes'         => klaro_geo_get_default_purpose_translations(),
			'purposeItem'      => array(
				'service'  => 'service',
				'services' => 'services',
			),
			'service'          => array(
				'disableAll'        => array(
					'title'       => 'Enable or disable all services',
					'description' => 'Use this switch to enable or disable all services.',
				),
				'optOut'            => array(
					'title'       => '(opt-out)',
					'description' => 'This services is loaded by default (but you can opt out)',
				),
				'required'          => array(
					'title'       => '(always required)',
					'description' => 'This services is always required',
				),
				'purpose'           => 'purpose',
				'purposes'          => 'purposes',
				'contextualConsent' => array(
					'description'  => 'Would you like to consent to {title}?',
					'acceptOnce'   => 'Yes',
					'acceptAlways' => 'Always',
				),
			),
			'ok'               => 'OK',
			'save'             => 'Save',
			'poweredBy'        => 'Realized with Klaro!',
		),
	);
}

/**
 * Get default templates for Klaro Geo
 *
 * The three built-in templates share one configuration. "strict" is the same
 * opt-in configuration as "default" under another name; "relaxed" is opt-out.
 *
 * @return array The default templates
 */
function klaro_geo_get_default_templates() {
	$opt_in = array(
		'version'                => 1,
		'elementID'              => 'klaro',
		'styling'                => array(
			'theme' => array(
				'color'    => 'light',
				'position' => 'top',
				'width'    => 'wide',
			),
		),
		'htmlTexts'              => true,
		'embedded'               => false,
		'groupByPurpose'         => true,
		'storageMethod'          => 'cookie',
		'cookieName'             => 'klaro',
		'cookieExpiresAfterDays' => 365,
		'cookieDomain'           => '', // Empty string means auto-detect with leading dot for subdomain sharing
		'default'                => false,
		'mustConsent'            => true,
		'acceptAll'              => true,
		'hideDeclineAll'         => false,
		'hideLearnMore'          => false,
		'noticeAsModal'          => true,
		'disablePoweredBy'       => true,
		'consent_mode'           => 'none',
		'translations'           => klaro_geo_get_default_translations(),
	);

	$opt_out = array_merge(
		$opt_in,
		array(
			'default'        => true,
			'mustConsent'    => false,
			'hideDeclineAll' => true,
			'noticeAsModal'  => false,
		)
	);

	return array(
		'default' => array(
			'name'        => 'Default Template',
			'description' => 'The default template used when no location-specific template is found',
			'config'      => $opt_in,
		),
		'strict'  => array(
			'name'        => 'Strict Opt-In',
			'description' => 'Requires explicit consent for all services (opt-in)',
			'config'      => $opt_in,
		),
		'relaxed' => array(
			'name'        => 'Relaxed Opt-Out',
			'description' => 'Assumes consent for all services (opt-out)',
			'config'      => $opt_out,
		),
	);
}

/**
 * Get default services for Klaro Geo
 *
 * @return array The default services
 */
function klaro_geo_get_default_services() {
	// Define the default services
	return array(
		array(
			'name'         => 'google-tag-manager',
			'title'        => 'Google Tag Manager',
			// Use Global Setting: each template's Required decides, so GTM can be
			// required in most regions and consent-gated where the law calls for it.
			'required'     => null,
			'purposes'     => array( 'infrastructure' ),
			'default'      => false,
			'cookies'      => array(),
			'translations' => array(
				'zz' => array(
					'title'       => 'Google Tag Manager',
					'description' => 'Google Tag Manager is a tag management system that allows you to quickly and easily update tracking codes and related code fragments collectively known as tags on your website or mobile app.',
				),
			),
		),
		array(
			'name'         => 'google-analytics',
			'title'        => 'Google Analytics',
			'required'     => false,
			'purposes'     => array( 'analytics' ),
			'default'      => false,
			'cookies'      => array(),
			'translations' => array(
				'zz' => array(
					'title'       => 'Google Analytics',
					'description' => 'Google Analytics is a web analytics service that tracks and reports website traffic to help you understand how visitors interact with your website.',
				),
			),
		),
		array(
			'name'         => 'google-ads',
			'title'        => 'Google Ads',
			'required'     => false,
			'purposes'     => array( 'advertising' ),
			'default'      => false,
			'cookies'      => array(),
			'translations' => array(
				'zz' => array(
					'title'       => 'Google Ads',
					'description' => 'Google Ads is an online advertising platform developed by Google, where advertisers pay to display brief advertisements, service offerings, product listings, and video content to web users.',
				),
			),
		),
		// Consent Mode Services - these control Google Consent Mode signals
		array(
			'name'                    => 'analytics-storage',
			'title'                   => 'Analytics Storage',
			'required'                => false,
			'purposes'                => array( 'analytics' ),
			'default'                 => false,
			'cookies'                 => array(),
			'is_consent_mode_service' => true,
			'consent_mode_key'        => 'analytics_storage',
			'translations'            => array(
				'zz' => array(
					'title'       => 'Analytics Storage',
					'description' => 'Enables storage (such as cookies) related to analytics, e.g., visit duration. This controls the analytics_storage consent signal.',
				),
			),
		),
		array(
			'name'                    => 'ad-storage',
			'title'                   => 'Advertising Storage',
			'required'                => false,
			'purposes'                => array( 'advertising' ),
			'default'                 => false,
			'cookies'                 => array(),
			'is_consent_mode_service' => true,
			'consent_mode_key'        => 'ad_storage',
			'translations'            => array(
				'zz' => array(
					'title'       => 'Advertising Storage',
					'description' => 'Enables storage (such as cookies) related to advertising. This controls the ad_storage consent signal.',
				),
			),
		),
		array(
			'name'                    => 'ad-user-data',
			'title'                   => 'Ad User Data',
			'required'                => false,
			'purposes'                => array( 'advertising' ),
			'default'                 => false,
			'cookies'                 => array(),
			'is_consent_mode_service' => true,
			'consent_mode_key'        => 'ad_user_data',
			'parent_service'          => 'ad-storage',
			'translations'            => array(
				'zz' => array(
					'title'       => 'Ad User Data',
					'description' => 'Controls whether user data can be sent to Google for advertising purposes. This controls the ad_user_data consent signal.',
				),
			),
		),
		array(
			'name'                    => 'ad-personalization',
			'title'                   => 'Ad Personalization',
			'required'                => false,
			'purposes'                => array( 'advertising' ),
			'default'                 => false,
			'cookies'                 => array(),
			'is_consent_mode_service' => true,
			'consent_mode_key'        => 'ad_personalization',
			'parent_service'          => 'ad-storage',
			'translations'            => array(
				'zz' => array(
					'title'       => 'Ad Personalization',
					'description' => 'Controls whether personalized advertising is enabled. This controls the ad_personalization consent signal.',
				),
			),
		),
	);
}

/**
 * The config a template gets when its stored config cannot be used (missing,
 * or not an array): the built-in default template's config, made to fail
 * closed. Nothing is granted by default and nothing is undeclinable.
 *
 * acceptAll comes from the built-in config (true): without it the notice's
 * "That's ok" button saves the default consents, which declines every service.
 *
 * @return array
 */
function klaro_geo_get_fail_closed_template_config() {
	$config = klaro_geo_get_default_templates()['default']['config'];

	$config['default']                   = false;
	$config['required']                  = false;
	$config['noAutoLoad']                = false;
	$config['showDescriptionEmptyStore'] = true;
	$config['autoFocus']                 = false;
	$config['showNoticeTitle']           = false;
	$config['cookiePath']                = '/';

	return $config;
}
