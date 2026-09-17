/**
 * Klaro Geo — Client-Side Geo Resolution (cache-safe mode)
 *
 * Active only when the cache-safe client resolution mode is on
 * (klaro_geo_geo_resolution_mode = 'client'). The server ships a
 * visitor-independent payload (window.klaroGeoClientGeo) containing the
 * location→template rules map and a full Klaro config per candidate template;
 * this file resolves the visitor's geo in the browser and selects the template
 * BEFORE Klaro initializes, so no visitor-specific value ever lives in
 * cacheable HTML.
 *
 * Resolution fallback chain (first success wins, never silently fails):
 *   1. geoip-ajax — the GeoIP Detection plugin's AJAX endpoint (admin-ajax.php
 *      is excluded from page caches, so it is per-visitor accurate)
 *   2. timezone  — country inferred from Intl.DateTimeFormat().resolvedOptions()
 *      .timeZone via the bundled TZ_COUNTRY map (no network; region stays empty)
 *   3. fallback  — empty geo; the rules map yields the fallback template
 *
 * The template resolution logic mirrors
 * Klaro_Geo_Country_Settings::get_effective_settings() — a shared parity
 * fixture (tests/fixtures/geo-resolution-parity.json) runs against both
 * implementations so they cannot drift.
 *
 * Spec: specs/client-side-geo.spec.yaml
 */
(function() {
    'use strict';

    function log() {
        if (typeof window.klaroGeoLog === 'function') {
            window.klaroGeoLog.apply(null, arguments);
        }
    }

    // IANA timezone → ISO 3166-1 alpha-2 country. Generated from tzdata
    // zone1970.tab plus common deprecated aliases some environments still return.
    var TZ_COUNTRY = {
        'Africa/Abidjan':'CI','Africa/Algiers':'DZ','Africa/Asmera':'ER','Africa/Bissau':'GW',
        'Africa/Cairo':'EG','Africa/Casablanca':'MA','Africa/Ceuta':'ES','Africa/El_Aaiun':'EH',
        'Africa/Johannesburg':'ZA','Africa/Juba':'SS','Africa/Khartoum':'SD','Africa/Lagos':'NG',
        'Africa/Maputo':'MZ','Africa/Monrovia':'LR','Africa/Nairobi':'KE','Africa/Ndjamena':'TD',
        'Africa/Sao_Tome':'ST','Africa/Tripoli':'LY','Africa/Tunis':'TN','Africa/Windhoek':'NA',
        'America/Adak':'US','America/Anchorage':'US','America/Araguaina':'BR','America/Argentina/Buenos_Aires':'AR',
        'America/Argentina/Catamarca':'AR','America/Argentina/Cordoba':'AR','America/Argentina/Jujuy':'AR','America/Argentina/La_Rioja':'AR',
        'America/Argentina/Mendoza':'AR','America/Argentina/Rio_Gallegos':'AR','America/Argentina/Salta':'AR','America/Argentina/San_Juan':'AR',
        'America/Argentina/San_Luis':'AR','America/Argentina/Tucuman':'AR','America/Argentina/Ushuaia':'AR','America/Asuncion':'PY',
        'America/Bahia':'BR','America/Bahia_Banderas':'MX','America/Barbados':'BB','America/Belem':'BR',
        'America/Belize':'BZ','America/Boa_Vista':'BR','America/Bogota':'CO','America/Boise':'US',
        'America/Buenos_Aires':'AR','America/Cambridge_Bay':'CA','America/Campo_Grande':'BR','America/Cancun':'MX',
        'America/Caracas':'VE','America/Cayenne':'GF','America/Chicago':'US','America/Chihuahua':'MX',
        'America/Ciudad_Juarez':'MX','America/Coral_Harbour':'CA','America/Costa_Rica':'CR','America/Coyhaique':'CL',
        'America/Cuiaba':'BR','America/Danmarkshavn':'GL','America/Dawson':'CA','America/Dawson_Creek':'CA',
        'America/Denver':'US','America/Detroit':'US','America/Edmonton':'CA','America/Eirunepe':'BR',
        'America/El_Salvador':'SV','America/Fort_Nelson':'CA','America/Fortaleza':'BR','America/Glace_Bay':'CA',
        'America/Godthab':'GL','America/Goose_Bay':'CA','America/Grand_Turk':'TC','America/Guatemala':'GT',
        'America/Guayaquil':'EC','America/Guyana':'GY','America/Halifax':'CA','America/Havana':'CU',
        'America/Hermosillo':'MX','America/Indiana/Indianapolis':'US','America/Indiana/Knox':'US','America/Indiana/Marengo':'US',
        'America/Indiana/Petersburg':'US','America/Indiana/Tell_City':'US','America/Indiana/Vevay':'US','America/Indiana/Vincennes':'US',
        'America/Indiana/Winamac':'US','America/Inuvik':'CA','America/Iqaluit':'CA','America/Jamaica':'JM',
        'America/Juneau':'US','America/Kentucky/Louisville':'US','America/Kentucky/Monticello':'US','America/La_Paz':'BO',
        'America/Lima':'PE','America/Los_Angeles':'US','America/Maceio':'BR','America/Managua':'NI',
        'America/Manaus':'BR','America/Martinique':'MQ','America/Matamoros':'MX','America/Mazatlan':'MX',
        'America/Menominee':'US','America/Merida':'MX','America/Metlakatla':'US','America/Mexico_City':'MX',
        'America/Miquelon':'PM','America/Moncton':'CA','America/Monterrey':'MX','America/Montevideo':'UY',
        'America/Montreal':'CA','America/New_York':'US','America/Nome':'US','America/Noronha':'BR',
        'America/North_Dakota/Beulah':'US','America/North_Dakota/Center':'US','America/North_Dakota/New_Salem':'US','America/Nuuk':'GL',
        'America/Ojinaga':'MX','America/Panama':'PA','America/Paramaribo':'SR','America/Phoenix':'US',
        'America/Port-au-Prince':'HT','America/Porto_Acre':'BR','America/Porto_Velho':'BR','America/Puerto_Rico':'PR',
        'America/Punta_Arenas':'CL','America/Rankin_Inlet':'CA','America/Recife':'BR','America/Regina':'CA',
        'America/Resolute':'CA','America/Rio_Branco':'BR','America/Santarem':'BR','America/Santiago':'CL',
        'America/Santo_Domingo':'DO','America/Sao_Paulo':'BR','America/Scoresbysund':'GL','America/Sitka':'US',
        'America/St_Johns':'CA','America/Swift_Current':'CA','America/Tegucigalpa':'HN','America/Thule':'GL',
        'America/Tijuana':'MX','America/Toronto':'CA','America/Vancouver':'CA','America/Whitehorse':'CA',
        'America/Winnipeg':'CA','America/Yakutat':'US','Antarctica/Casey':'AQ','Antarctica/Davis':'AQ',
        'Antarctica/Macquarie':'AU','Antarctica/Mawson':'AQ','Antarctica/Palmer':'AQ','Antarctica/Rothera':'AQ',
        'Antarctica/Troll':'AQ','Antarctica/Vostok':'AQ','Asia/Almaty':'KZ','Asia/Amman':'JO',
        'Asia/Anadyr':'RU','Asia/Aqtau':'KZ','Asia/Aqtobe':'KZ','Asia/Ashgabat':'TM',
        'Asia/Atyrau':'KZ','Asia/Baghdad':'IQ','Asia/Baku':'AZ','Asia/Bangkok':'TH',
        'Asia/Barnaul':'RU','Asia/Beirut':'LB','Asia/Bishkek':'KG','Asia/Calcutta':'IN',
        'Asia/Chita':'RU','Asia/Chongqing':'CN','Asia/Colombo':'LK','Asia/Dacca':'BD',
        'Asia/Damascus':'SY','Asia/Dhaka':'BD','Asia/Dili':'TL','Asia/Dubai':'AE',
        'Asia/Dushanbe':'TJ','Asia/Famagusta':'CY','Asia/Gaza':'PS','Asia/Harbin':'CN',
        'Asia/Hebron':'PS','Asia/Ho_Chi_Minh':'VN','Asia/Hong_Kong':'HK','Asia/Hovd':'MN',
        'Asia/Irkutsk':'RU','Asia/Jakarta':'ID','Asia/Jayapura':'ID','Asia/Jerusalem':'IL',
        'Asia/Kabul':'AF','Asia/Kamchatka':'RU','Asia/Karachi':'PK','Asia/Kashgar':'CN',
        'Asia/Kathmandu':'NP','Asia/Katmandu':'NP','Asia/Khandyga':'RU','Asia/Kolkata':'IN',
        'Asia/Krasnoyarsk':'RU','Asia/Kuching':'MY','Asia/Macao':'MO','Asia/Macau':'MO',
        'Asia/Magadan':'RU','Asia/Makassar':'ID','Asia/Manila':'PH','Asia/Nicosia':'CY',
        'Asia/Novokuznetsk':'RU','Asia/Novosibirsk':'RU','Asia/Omsk':'RU','Asia/Oral':'KZ',
        'Asia/Pontianak':'ID','Asia/Pyongyang':'KP','Asia/Qatar':'QA','Asia/Qostanay':'KZ',
        'Asia/Qyzylorda':'KZ','Asia/Rangoon':'MM','Asia/Riyadh':'SA','Asia/Saigon':'VN',
        'Asia/Sakhalin':'RU','Asia/Samarkand':'UZ','Asia/Seoul':'KR','Asia/Shanghai':'CN',
        'Asia/Singapore':'SG','Asia/Srednekolymsk':'RU','Asia/Taipei':'TW','Asia/Tashkent':'UZ',
        'Asia/Tbilisi':'GE','Asia/Tehran':'IR','Asia/Thimbu':'BT','Asia/Thimphu':'BT',
        'Asia/Tokyo':'JP','Asia/Tomsk':'RU','Asia/Ulaanbaatar':'MN','Asia/Ulan_Bator':'MN',
        'Asia/Urumqi':'CN','Asia/Ust-Nera':'RU','Asia/Vladivostok':'RU','Asia/Yakutsk':'RU',
        'Asia/Yangon':'MM','Asia/Yekaterinburg':'RU','Asia/Yerevan':'AM','Atlantic/Azores':'PT',
        'Atlantic/Bermuda':'BM','Atlantic/Canary':'ES','Atlantic/Cape_Verde':'CV','Atlantic/Faeroe':'FO',
        'Atlantic/Faroe':'FO','Atlantic/Madeira':'PT','Atlantic/South_Georgia':'GS','Atlantic/Stanley':'FK',
        'Australia/ACT':'AU','Australia/Adelaide':'AU','Australia/Brisbane':'AU','Australia/Broken_Hill':'AU',
        'Australia/Darwin':'AU','Australia/Eucla':'AU','Australia/Hobart':'AU','Australia/Lindeman':'AU',
        'Australia/Lord_Howe':'AU','Australia/Melbourne':'AU','Australia/NSW':'AU','Australia/North':'AU',
        'Australia/Perth':'AU','Australia/Queensland':'AU','Australia/South':'AU','Australia/Sydney':'AU',
        'Australia/Tasmania':'AU','Australia/Victoria':'AU','Australia/West':'AU','Europe/Andorra':'AD',
        'Europe/Astrakhan':'RU','Europe/Athens':'GR','Europe/Belgrade':'RS','Europe/Berlin':'DE',
        'Europe/Brussels':'BE','Europe/Bucharest':'RO','Europe/Budapest':'HU','Europe/Chisinau':'MD',
        'Europe/Dublin':'IE','Europe/Gibraltar':'GI','Europe/Helsinki':'FI','Europe/Istanbul':'TR',
        'Europe/Kaliningrad':'RU','Europe/Kiev':'UA','Europe/Kirov':'RU','Europe/Kyiv':'UA',
        'Europe/Lisbon':'PT','Europe/London':'GB','Europe/Madrid':'ES','Europe/Malta':'MT',
        'Europe/Minsk':'BY','Europe/Moscow':'RU','Europe/Paris':'FR','Europe/Prague':'CZ',
        'Europe/Riga':'LV','Europe/Rome':'IT','Europe/Samara':'RU','Europe/Saratov':'RU',
        'Europe/Simferopol':'RU','Europe/Sofia':'BG','Europe/Tallinn':'EE','Europe/Tirane':'AL',
        'Europe/Ulyanovsk':'RU','Europe/Vienna':'AT','Europe/Vilnius':'LT','Europe/Volgograd':'RU',
        'Europe/Warsaw':'PL','Europe/Zurich':'CH','Indian/Chagos':'IO','Indian/Maldives':'MV',
        'Indian/Mauritius':'MU','Pacific/Apia':'WS','Pacific/Auckland':'NZ','Pacific/Bougainville':'PG',
        'Pacific/Chatham':'NZ','Pacific/Easter':'CL','Pacific/Efate':'VU','Pacific/Fakaofo':'TK',
        'Pacific/Fiji':'FJ','Pacific/Galapagos':'EC','Pacific/Gambier':'PF','Pacific/Guadalcanal':'SB',
        'Pacific/Guam':'GU','Pacific/Honolulu':'US','Pacific/Kanton':'KI','Pacific/Kiritimati':'KI',
        'Pacific/Kosrae':'FM','Pacific/Kwajalein':'MH','Pacific/Marquesas':'PF','Pacific/Nauru':'NR',
        'Pacific/Niue':'NU','Pacific/Norfolk':'NF','Pacific/Noumea':'NC','Pacific/Pago_Pago':'AS',
        'Pacific/Palau':'PW','Pacific/Pitcairn':'PN','Pacific/Ponape':'FM','Pacific/Port_Moresby':'PG',
        'Pacific/Rarotonga':'CK','Pacific/Tahiti':'PF','Pacific/Tarawa':'KI','Pacific/Tongatapu':'TO',
        'Pacific/Truk':'FM','US/Alaska':'US','US/Arizona':'US','US/Central':'US',
        'US/Eastern':'US','US/Hawaii':'US','US/Mountain':'US','US/Pacific':'US'
    };

    /**
     * Infer country from the browser timezone. Returns '' when unavailable.
     */
    function timezoneToCountry() {
        try {
            if (typeof Intl === 'undefined' || typeof Intl.DateTimeFormat !== 'function') {
                return '';
            }
            var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (tz && TZ_COUNTRY[tz]) {
                return TZ_COUNTRY[tz];
            }
        } catch (e) {
            // fall through
        }
        return '';
    }

    /**
     * Extract {country, region} from a geoip-detect record (AJAX JSON response).
     * Mirrors the server-side reads: country.iso_code and the MOST SPECIFIC
     * subdivision (last element of subdivisions). Returns null on lookup failure.
     */
    function parseGeoRecord(record) {
        if (!record || typeof record !== 'object') {
            return null;
        }
        if (record.is_empty === true) {
            return null;
        }
        var country = (record.country && record.country.iso_code) ? String(record.country.iso_code) : '';
        if (!country) {
            return null;
        }
        var region = '';
        if (Array.isArray(record.subdivisions) && record.subdivisions.length > 0) {
            var mostSpecific = record.subdivisions[record.subdivisions.length - 1];
            if (mostSpecific && mostSpecific.iso_code) {
                region = String(mostSpecific.iso_code);
            }
        }
        return { country: country, region: region };
    }

    /**
     * Resolve the effective template for a location.
     *
     * MUST mirror Klaro_Geo_Country_Settings::get_effective_settings():
     *   region override > country setting > 'inherit' → default > default,
     * then validate the result against the shipped candidate templates.
     *
     * @param {Object} rules   {defaultTemplate, visibleCountries, countries}
     * @param {Object} templates Candidate templates map (only keys matter here)
     * @param {string} country ISO country code ('' = unknown)
     * @param {string} region  ISO region code ('' = none)
     * @returns {{template: string, source: string}}
     */
    function resolveEffectiveTemplate(rules, templates, country, region) {
        var template = rules.defaultTemplate;
        var source = 'default';

        var visible = rules.visibleCountries || [];
        if (country && visible.indexOf(country) !== -1) {
            var countrySettings = rules.countries ? rules.countries[country] : undefined;
            if (countrySettings) {
                if (countrySettings.template === 'inherit') {
                    template = rules.defaultTemplate;
                    source = 'fallback';
                } else {
                    if (typeof countrySettings.template !== 'undefined') {
                        template = countrySettings.template;
                    }
                    source = 'country';
                }

                if (region && countrySettings.regions &&
                    typeof countrySettings.regions[region] !== 'undefined') {
                    var regionTemplate = countrySettings.regions[region];
                    // Normalized server-side, but tolerate the object form too
                    if (regionTemplate && typeof regionTemplate === 'object') {
                        regionTemplate = regionTemplate.template || '';
                    }
                    if (regionTemplate !== 'inherit') {
                        template = regionTemplate;
                        source = 'region';
                    }
                }
            }
            // Country visible but without settings: default template, source 'default'
        }

        // Validation: the selected template must be among the shipped candidates
        if (!templates || typeof templates[template] === 'undefined') {
            log('Klaro Geo client geo: template "' + template + '" not shipped, falling back to "' + rules.defaultTemplate + '"');
            template = rules.defaultTemplate;
            source = 'fallback';
        }

        return { template: template, source: source };
    }

    /**
     * Fetch geo from the GeoIP Detection plugin's AJAX endpoint.
     * Resolves {country, region} or rejects on failure/timeout/empty record.
     */
    function fetchGeoViaAjax(geoAjax, timeoutMs) {
        return new Promise(function(resolve, reject) {
            if (!geoAjax || !geoAjax.url || typeof window.fetch !== 'function') {
                reject(new Error('fetch unavailable'));
                return;
            }

            var controller = (typeof AbortController === 'function') ? new AbortController() : null;
            var timedOut = false;
            var timer = setTimeout(function() {
                timedOut = true;
                if (controller) {
                    controller.abort();
                }
                reject(new Error('geo lookup timeout after ' + timeoutMs + 'ms'));
            }, timeoutMs);

            var url = geoAjax.url + (geoAjax.url.indexOf('?') === -1 ? '?' : '&') +
                'action=' + encodeURIComponent(geoAjax.action);

            window.fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined
            }).then(function(response) {
                if (!response.ok) {
                    throw new Error('geo endpoint HTTP ' + response.status);
                }
                return response.json();
            }).then(function(record) {
                clearTimeout(timer);
                if (timedOut) {
                    return;
                }
                var geo = parseGeoRecord(record);
                if (geo) {
                    resolve(geo);
                } else {
                    reject(new Error('geo lookup returned empty record'));
                }
            }).catch(function(err) {
                clearTimeout(timer);
                if (!timedOut) {
                    reject(err);
                }
            });
        });
    }

    function nowMs() {
        try {
            if (window.performance && typeof performance.now === 'function') {
                return performance.now();
            }
        } catch (e) {
            // fall through
        }
        return Date.now();
    }

    function elapsedMs(startMs) {
        return Math.max(0, Math.round(nowMs() - startMs));
    }

    /**
     * Apply a resolved geo: select the template, apply GPC, expose klaroConfig,
     * update klaroConsentData, emit consent-mode updates and dataLayer events,
     * then initialize Klaro.
     *
     * @param {Object} payload The window.klaroGeoClientGeo payload.
     * @param {Object} geo     {country, region, source, latencyMs}
     */
    function applyResolution(payload, geo) {
        var resolution = resolveEffectiveTemplate(payload.rules, payload.templates, geo.country, geo.region);
        var templateKey = resolution.template;
        var templateSource = resolution.source;
        var template = payload.templates[templateKey];

        if (!template) {
            // Terminal safety net: ship-time guarantees include the default
            // template, but never leave the page without a consent manager.
            var keys = Object.keys(payload.templates || {});
            if (keys.length === 0) {
                if (window.console && console.error) {
                    console.error('[klaro-geo] Client geo payload contains no templates; Klaro not initialized');
                }
                return;
            }
            templateKey = keys[0];
            templateSource = 'fallback';
            template = payload.templates[templateKey];
        }

        log('Klaro Geo client geo: resolved', {
            country: geo.country, region: geo.region, geoSource: geo.source,
            template: templateKey, templateSource: templateSource, latencyMs: geo.latencyMs
        });

        var config = template.config;

        // ----- GPC (mirrors the server-mode inline GPC block) -----
        var gpc = { detected: false, enabled: !!template.gpcEnabled, affectedServices: [] };
        if (gpc.enabled && navigator.globalPrivacyControl === true) {
            gpc.detected = true;
            if (config && config.services) {
                for (var i = 0; i < config.services.length; i++) {
                    var svc = config.services[i];
                    if (svc.gpc_sensitive && !svc.required) {
                        svc.default = false;
                        gpc.affectedServices.push(svc.name);
                    }
                }
            }
        }
        window.klaroGeoGPC = gpc;
        window.klaroGeo = window.klaroGeo || {};
        window.klaroGeo.gpc = gpc;

        // ----- Consent mode update -----
        // The <head> defaults came from the fallback template. If a different
        // template was selected (or GPC applies), update the consent state.
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function() { window.dataLayer.push(arguments); };

        var selectedDefaults = template.consentDefaults || {};
        if (templateKey !== payload.rules.defaultTemplate || gpc.detected) {
            var consentUpdate = {};
            var key;
            for (key in selectedDefaults) {
                if (Object.prototype.hasOwnProperty.call(selectedDefaults, key)) {
                    consentUpdate[key] = selectedDefaults[key];
                }
            }
            if (gpc.detected && config && config.services) {
                for (var j = 0; j < config.services.length; j++) {
                    var gpcSvc = config.services[j];
                    if (gpcSvc.gpc_sensitive) {
                        if (gpcSvc.is_consent_mode_service && gpcSvc.consent_mode_key) {
                            consentUpdate[gpcSvc.consent_mode_key] = 'denied';
                        }
                        consentUpdate[gpcSvc.name.replace(/-/g, '_') + '_consent'] = 'denied';
                    }
                }
            }
            window.gtag('consent', 'update', consentUpdate);
            log('Klaro Geo client geo: consent update pushed for template "' + templateKey + '"' +
                (gpc.detected ? ' (GPC applied)' : ''));
        }

        // gtag flags for the selected template (head already set the fallback
        // template's flags when true; 'set' is additive-only, so only emit trues)
        var gtagSettings = template.gtagSettings || {};
        if (gtagSettings.ads_data_redaction === 'true') {
            window.gtag('set', 'ads_data_redaction', true);
        }
        if (gtagSettings.url_passthrough === 'true') {
            window.gtag('set', 'url_passthrough', true);
        }

        // ----- klaroConsentData (consent receipts + GTM mode detection) -----
        if (window.klaroConsentData) {
            window.klaroConsentData.templateName = templateKey;
            window.klaroConsentData.templateSource = templateSource;
            window.klaroConsentData.detectedCountry = geo.country || '';
            window.klaroConsentData.detectedRegion = geo.region || '';
            window.klaroConsentData.adminOverride = false;
            window.klaroConsentData.enableConsentLogging = !!template.enableConsentLogging;
            if (template.templateSettings) {
                window.klaroConsentData.templateSettings = {
                    consentModalTitle: template.templateSettings.consentModalTitle,
                    consentModalDescription: template.templateSettings.consentModalDescription,
                    acceptAllText: template.templateSettings.acceptAllText,
                    declineAllText: template.templateSettings.declineAllText,
                    defaultConsent: template.templateSettings.defaultConsent,
                    requiredConsent: template.templateSettings.requiredConsent,
                    config: {
                        consent_mode_settings: {
                            consent_defaults: selectedDefaults,
                            gtag_settings: {
                                ads_data_redaction: gtagSettings.ads_data_redaction === 'true',
                                url_passthrough: gtagSettings.url_passthrough === 'true'
                            }
                        }
                    }
                };
            }
        }

        // ----- Expose the selected config -----
        window.klaroConfig = config;

        // ----- klaroConfigLoaded dataLayer event (after resolution, once) -----
        var latestReceipt = null;
        try {
            if (typeof window.getLatestConsentReceipt === 'function') {
                latestReceipt = window.getLatestConsentReceipt();
            } else {
                var existingData = window.localStorage.getItem('klaro_consent_receipts');
                if (existingData) {
                    var receipts = JSON.parse(existingData);
                    if (Array.isArray(receipts) && receipts.length > 0) {
                        latestReceipt = receipts[receipts.length - 1];
                    }
                }
            }
        } catch (e) {
            if (window.console && console.error) {
                console.error('Error retrieving latest consent receipt:', e);
            }
        }

        var configLoadedData = {
            'event': 'Klaro Event',
            'eventSource': 'klaro-geo',
            'klaroEventName': 'klaroConfigLoaded',
            'klaroGeoConsentTemplate': templateKey,
            'klaroGeoTemplateSource': templateSource,
            'klaroGeoDetectedCountry': geo.country || null,
            'klaroGeoDetectedRegion': geo.region || null,
            'klaroGeoAdminOverride': false,
            'klaroGeoResolutionMode': 'client',
            'klaroGeoGeoSource': geo.source,
            'klaroGeoGeoLatencyMs': (typeof geo.latencyMs === 'number') ? geo.latencyMs : null,
            'klaroGeoEnableConsentLogging': !!template.enableConsentLogging,
            'klaroGeoGPCDetected': gpc.detected,
            'klaroGeoGPCEnabled': gpc.enabled,
            'klaroGeoGPCAffectedServices': gpc.affectedServices
        };

        // Add the consent receipt ID if available and active consent data exists
        // (mirrors the server-mode inline block)
        if (latestReceipt) {
            var storageName = (config && (config.storageName || config.cookieName)) || 'klaro';
            var hasActiveConsent = false;
            if (config && config.storageMethod === 'localStorage') {
                hasActiveConsent = window.localStorage.getItem(storageName) !== null;
            } else {
                hasActiveConsent = document.cookie.split(';').some(function(c) {
                    return c.trim().indexOf(storageName + '=') === 0;
                });
            }
            if (hasActiveConsent) {
                configLoadedData.klaroGeoConsentReceiptId = latestReceipt.receipt_id;
            }
        }

        window.dataLayer.push(configLoadedData);

        if (gpc.detected) {
            window.dataLayer.push({
                'event': 'Klaro Event',
                'eventSource': 'klaro-geo',
                'klaroEventName': 'gpcDetected',
                'klaroGeoGPCAffectedServices': gpc.affectedServices
            });
        }

        // ----- Initialize Klaro -----
        // If klaro.js already loaded, it found no window.klaroConfig and did not
        // auto-init: set it up now. If klaro.js has not loaded yet, it will
        // auto-init from the window.klaroConfig we just set.
        if (window.klaro && typeof window.klaro.setup === 'function') {
            window.klaro.setup(window.klaroConfig);
            log('Klaro Geo client geo: klaro.setup() called');
        } else {
            log('Klaro Geo client geo: klaroConfig set; Klaro will auto-init on load');
        }
    }

    /**
     * Run the fallback chain and apply the outcome.
     * Returns a promise (exposed for tests); never rejects.
     */
    function resolveAndApply(payload) {
        var start = nowMs();

        return fetchGeoViaAjax(payload.geoAjax, payload.timeoutMs || 2500)
            .then(function(geo) {
                return {
                    country: geo.country,
                    region: geo.region,
                    source: 'geoip-ajax',
                    latencyMs: elapsedMs(start)
                };
            })
            .catch(function(err) {
                log('Klaro Geo client geo: AJAX lookup failed (' + (err && err.message) + ')');

                if (payload.timezoneFallback) {
                    var tzCountry = timezoneToCountry();
                    if (tzCountry) {
                        log('Klaro Geo client geo: falling back to timezone-derived country ' + tzCountry);
                        return {
                            country: tzCountry,
                            region: '',
                            source: 'timezone',
                            latencyMs: elapsedMs(start)
                        };
                    }
                    log('Klaro Geo client geo: timezone fallback could not resolve a country');
                }

                log('Klaro Geo client geo: falling back to fallback template (no geo)');
                return {
                    country: '',
                    region: '',
                    source: 'fallback',
                    latencyMs: elapsedMs(start)
                };
            })
            .then(function(geo) {
                applyResolution(payload, geo);
                return geo;
            })
            .catch(function(err) {
                // applyResolution must never leave the page without Klaro; if it
                // threw, log loudly — this indicates a payload bug, not a geo miss.
                if (window.console && console.error) {
                    console.error('[klaro-geo] Client geo resolution failed:', err);
                }
            });
    }

    // Internals exposed for unit tests
    var api = {
        resolveEffectiveTemplate: resolveEffectiveTemplate,
        parseGeoRecord: parseGeoRecord,
        timezoneToCountry: timezoneToCountry,
        fetchGeoViaAjax: fetchGeoViaAjax,
        applyResolution: applyResolution,
        resolveAndApply: resolveAndApply,
        TZ_COUNTRY: TZ_COUNTRY
    };

    var payload = window.klaroGeoClientGeo;
    if (payload && payload.mode === 'client') {
        payload._internal = api;
        // deferStart is a test hook: unit tests drive resolveAndApply() directly.
        if (!payload.deferStart) {
            payload.resolution = resolveAndApply(payload);
        }
    }

    // CommonJS export for unit tests (jsdom)
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
}());
