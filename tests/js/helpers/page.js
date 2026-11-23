/**
 * The page as js/klaro-geo.js finds it.
 *
 * On a real page the inline script PHP prints (klaro_geo_render_frontend_payload())
 * defines window.dataLayer, window.gtag, window.klaroGeo and
 * window.klaroConsentData, and the bootstrap sets window.klaroGeo.gpc when it
 * applies the template. The plugin's scripts rely on that and do not re-check
 * it, so a test that loads them has to provide the same things. The values
 * follow specs/frontend-data.spec.yaml; pass overrides for what a test is about.
 *
 * @param {Object} consentData Overrides for window.klaroConsentData.
 * @param {Object} klaroGeo    Overrides for window.klaroGeo (a stub queue, useGTM, gpc...).
 */
function setUpPage(consentData, klaroGeo) {
    window.dataLayer = [];
    window.gtag = jest.fn();
    window.klaroGeo = Object.assign({
        gpc: { detected: false, enabled: true, affectedServices: [] }
    }, klaroGeo);
    window.klaroConsentData = Object.assign({
        gtmId: '',
        consentModeType: 'basic',
        templateName: 'default',
        templateSource: 'fallback',
        detectedCountry: '',
        detectedRegion: '',
        adminOverride: false,
        consentReceipts: false,
        enableConsentLogging: false,
        consentMode: 'v2',
        suppressConsentsEvents: true,
        consentModeServices: {},
        parentChildMap: {},
        templateSettings: {
            config: {
                consent_mode_settings: {
                    consent_defaults: {},
                    gtag_settings: { ads_data_redaction: false, url_passthrough: false }
                }
            }
        }
    }, consentData);
}

/**
 * Load js/klaro-geo-bootstrap.js and return the functions it exports.
 *
 * The script starts itself from window.klaroGeoTemplatePayload when it loads,
 * as on a page, so a payload has to be there: here a server-mode one with a
 * single empty template. Tests then call the exported functions with their
 * own payloads.
 */
function loadBootstrap() {
    setUpPage();
    window.klaroGeoTemplatePayload = {
        mode: 'server',
        resolved: {
            template: 'default',
            templateSource: 'fallback',
            country: '',
            region: '',
            geoSource: 'server',
            adminOverride: false
        },
        rules: { defaultTemplate: 'default' },
        templates: {
            'default': {
                config: { services: [] },
                consentDefaults: {},
                gtagSettings: { ads_data_redaction: 'false', url_passthrough: 'false' },
                enableConsentLogging: false,
                gpcEnabled: false,
                templateSettings: {}
            }
        },
        consentReceipts: false
    };

    // Loaded first, as on the page: the bootstrap uses window.KlaroGeoEvents.
    require('../../../js/klaro-geo-events.js');
    const api = require('../../../js/klaro-geo-bootstrap.js');

    delete window.klaroGeoTemplatePayload;
    delete window.klaroConfig;
    return api;
}

module.exports = { setUpPage: setUpPage, loadBootstrap: loadBootstrap };
