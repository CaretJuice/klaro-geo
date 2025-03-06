<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// List of all options to clean up
$klaro_options = array(
    'klaro_geo_version',
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
    'klaro_geo_services',
    'klaro_geo_gtm_oninit',
    'klaro_geo_gtm_onaccept',
    'klaro_geo_gtm_ondecline',
    'klaro_geo_debug_countries',
    'klaro_geo_cleanup_on_deactivate'
);

// Remove each option
foreach ($klaro_options as $option) {
    delete_option($option);
}

// Log cleanup if WP_DEBUG is enabled
if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
    error_log('[Klaro Geo] All plugin settings removed during uninstall');
}
