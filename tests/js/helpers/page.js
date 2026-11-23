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

module.exports = { setUpPage: setUpPage };
