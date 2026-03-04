/**
 * GPC (Global Privacy Control) Tests
 *
 * Tests GPC detection logic, service default overrides,
 * consent mode updates, dataLayer events, and consent receipt fields.
 */

describe('GPC Detection', function() {
    beforeEach(function() {
        window.dataLayer = [];
        window.gtag = jest.fn();
        window.klaroGeo = {};
        delete window.klaroGeoGPC;
        // Default: no GPC signal
        Object.defineProperty(navigator, 'globalPrivacyControl', {
            value: undefined,
            writable: true,
            configurable: true
        });
    });

    afterEach(function() {
        window.dataLayer = [];
        delete window.klaroGeo;
        delete window.klaroGeoGPC;
        delete window.klaroConfig;
        jest.clearAllMocks();
    });

    /**
     * Helper: simulate the GPC detection block from klaro-config.js
     */
    function runGPCDetection(gpcConfig, klaroConfig) {
        window.klaroConfig = klaroConfig;

        var klaroGeoGPC = { detected: false, enabled: false, affectedServices: [] };
        klaroGeoGPC.enabled = gpcConfig.enabled;

        if (gpcConfig.enabled) {
            klaroGeoGPC.detected = (navigator.globalPrivacyControl === true);
            if (klaroGeoGPC.detected) {
                if (window.klaroConfig && window.klaroConfig.services) {
                    for (var i = 0; i < window.klaroConfig.services.length; i++) {
                        var svc = window.klaroConfig.services[i];
                        if (svc.gpc_sensitive && !svc.required) {
                            svc.default = false;
                            klaroGeoGPC.affectedServices.push(svc.name);
                        }
                    }
                }
            }
        }
        window.klaroGeo = window.klaroGeo || {};
        window.klaroGeo.gpc = klaroGeoGPC;
        window.klaroGeoGPC = klaroGeoGPC;
        return klaroGeoGPC;
    }

    // =========================================================================
    // GPC Signal Detection
    // =========================================================================

    test('should detect GPC when navigator.globalPrivacyControl is true', function() {
        navigator.globalPrivacyControl = true;
        var result = runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            { services: [] }
        );
        expect(result.detected).toBe(true);
        expect(result.enabled).toBe(true);
    });

    test('should not detect GPC when navigator.globalPrivacyControl is false', function() {
        navigator.globalPrivacyControl = false;
        var result = runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            { services: [] }
        );
        expect(result.detected).toBe(false);
    });

    test('should not detect GPC when navigator.globalPrivacyControl is undefined', function() {
        var result = runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            { services: [] }
        );
        expect(result.detected).toBe(false);
    });

    test('should not detect GPC when navigator.globalPrivacyControl is "1" (string)', function() {
        navigator.globalPrivacyControl = "1";
        var result = runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            { services: [] }
        );
        expect(result.detected).toBe(false);
    });

    test('should not process GPC when disabled globally', function() {
        navigator.globalPrivacyControl = true;
        var result = runGPCDetection(
            { enabled: false, gpc_purposes: ['advertising'] },
            { services: [{ name: 'ads', gpc_sensitive: true, default: true }] }
        );
        expect(result.enabled).toBe(false);
        expect(result.detected).toBe(false);
        // Service default should NOT be changed
        expect(window.klaroConfig.services[0].default).toBe(true);
    });

    // =========================================================================
    // Service Default Override
    // =========================================================================

    test('should set default to false for GPC-sensitive services', function() {
        navigator.globalPrivacyControl = true;
        var result = runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            {
                services: [
                    { name: 'google-ads', gpc_sensitive: true, default: true, required: false },
                    { name: 'analytics', gpc_sensitive: false, default: true, required: false }
                ]
            }
        );
        expect(window.klaroConfig.services[0].default).toBe(false);
        expect(window.klaroConfig.services[1].default).toBe(true);
        expect(result.affectedServices).toEqual(['google-ads']);
    });

    test('should not override required services even if GPC-sensitive', function() {
        navigator.globalPrivacyControl = true;
        var result = runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            {
                services: [
                    { name: 'essential', gpc_sensitive: true, default: true, required: true }
                ]
            }
        );
        expect(window.klaroConfig.services[0].default).toBe(true);
        expect(result.affectedServices).toEqual([]);
    });

    test('should handle services already defaulting to false', function() {
        navigator.globalPrivacyControl = true;
        var result = runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            {
                services: [
                    { name: 'ad-service', gpc_sensitive: true, default: false, required: false }
                ]
            }
        );
        // Still set to false and listed as affected
        expect(window.klaroConfig.services[0].default).toBe(false);
        expect(result.affectedServices).toEqual(['ad-service']);
    });

    test('should not affect non-GPC-sensitive services', function() {
        navigator.globalPrivacyControl = true;
        runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            {
                services: [
                    { name: 'analytics', gpc_sensitive: false, default: true, required: false },
                    { name: 'functional', gpc_sensitive: false, default: true, required: false }
                ]
            }
        );
        expect(window.klaroConfig.services[0].default).toBe(true);
        expect(window.klaroConfig.services[1].default).toBe(true);
    });

    test('should handle empty services array', function() {
        navigator.globalPrivacyControl = true;
        var result = runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            { services: [] }
        );
        expect(result.detected).toBe(true);
        expect(result.affectedServices).toEqual([]);
    });

    // =========================================================================
    // Consent Mode Override
    // =========================================================================

    test('should emit consent mode update for GPC-sensitive consent mode services', function() {
        navigator.globalPrivacyControl = true;
        runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            {
                services: [
                    {
                        name: 'ad-storage',
                        gpc_sensitive: true,
                        default: true,
                        required: false,
                        is_consent_mode_service: true,
                        consent_mode_key: 'ad_storage'
                    },
                    {
                        name: 'analytics-storage',
                        gpc_sensitive: false,
                        default: true,
                        required: false,
                        is_consent_mode_service: true,
                        consent_mode_key: 'analytics_storage'
                    }
                ]
            }
        );

        // Simulate the consent mode override block
        var gpc = window.klaroGeoGPC;
        if (gpc.detected && gpc.affectedServices.length > 0 && typeof gtag === 'function') {
            var gpcConsentOverride = {};
            for (var i = 0; i < window.klaroConfig.services.length; i++) {
                var svc = window.klaroConfig.services[i];
                if (svc.gpc_sensitive) {
                    if (svc.is_consent_mode_service && svc.consent_mode_key) {
                        gpcConsentOverride[svc.consent_mode_key] = 'denied';
                    }
                    gpcConsentOverride[svc.name.replace(/-/g, '_') + '_consent'] = 'denied';
                }
            }
            gtag('consent', 'update', gpcConsentOverride);
        }

        expect(window.gtag).toHaveBeenCalledWith('consent', 'update', {
            'ad_storage': 'denied',
            'ad_storage_consent': 'denied'
        });
    });

    test('should not emit consent mode update when no services affected', function() {
        navigator.globalPrivacyControl = true;
        runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            {
                services: [
                    { name: 'analytics', gpc_sensitive: false, default: true, required: false }
                ]
            }
        );

        var gpc = window.klaroGeoGPC;
        if (gpc.detected && gpc.affectedServices.length > 0 && typeof gtag === 'function') {
            gtag('consent', 'update', {});
        }

        expect(window.gtag).not.toHaveBeenCalled();
    });

    // =========================================================================
    // DataLayer Events
    // =========================================================================

    test('should include GPC fields in klaroConfigLoaded event', function() {
        navigator.globalPrivacyControl = true;
        var gpc = runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            {
                services: [
                    { name: 'ads', gpc_sensitive: true, default: true, required: false }
                ]
            }
        );

        // Simulate klaroConfigLoaded push
        var event = {
            'event': 'Klaro Event',
            'eventSource': 'klaro-geo',
            'klaroEventName': 'klaroConfigLoaded',
            'klaroGeoGPCDetected': gpc.detected,
            'klaroGeoGPCEnabled': gpc.enabled,
            'klaroGeoGPCAffectedServices': gpc.affectedServices
        };
        window.dataLayer.push(event);

        expect(window.dataLayer[0].klaroGeoGPCDetected).toBe(true);
        expect(window.dataLayer[0].klaroGeoGPCEnabled).toBe(true);
        expect(window.dataLayer[0].klaroGeoGPCAffectedServices).toEqual(['ads']);
    });

    test('should fire gpcDetected event when GPC is active', function() {
        navigator.globalPrivacyControl = true;
        var gpc = runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            {
                services: [
                    { name: 'ads', gpc_sensitive: true, default: true, required: false }
                ]
            }
        );

        if (gpc.detected) {
            window.dataLayer.push({
                'event': 'Klaro Event',
                'eventSource': 'klaro-geo',
                'klaroEventName': 'gpcDetected',
                'klaroGeoGPCAffectedServices': gpc.affectedServices
            });
        }

        expect(window.dataLayer.length).toBe(1);
        expect(window.dataLayer[0].klaroEventName).toBe('gpcDetected');
        expect(window.dataLayer[0].eventSource).toBe('klaro-geo');
    });

    test('should not fire gpcDetected event when GPC is not active', function() {
        navigator.globalPrivacyControl = false;
        var gpc = runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            { services: [] }
        );

        if (gpc.detected) {
            window.dataLayer.push({
                'event': 'Klaro Event',
                'eventSource': 'klaro-geo',
                'klaroEventName': 'gpcDetected'
            });
        }

        expect(window.dataLayer.length).toBe(0);
    });

    // =========================================================================
    // Klaro Consent Update gpcActive field
    // =========================================================================

    test('should include gpcActive: true in Klaro Consent Update when GPC detected', function() {
        navigator.globalPrivacyControl = true;
        runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            { services: [] }
        );

        var gpcActive = !!(window.klaroGeo && window.klaroGeo.gpc && window.klaroGeo.gpc.detected);
        expect(gpcActive).toBe(true);
    });

    test('should include gpcActive: false in Klaro Consent Update when GPC not detected', function() {
        runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            { services: [] }
        );

        var gpcActive = !!(window.klaroGeo && window.klaroGeo.gpc && window.klaroGeo.gpc.detected);
        expect(gpcActive).toBe(false);
    });

    // =========================================================================
    // Consent Receipt GPC Fields
    // =========================================================================

    test('should include GPC fields in consent receipt', function() {
        navigator.globalPrivacyControl = true;
        runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            {
                services: [
                    { name: 'ads', gpc_sensitive: true, default: true, required: false }
                ]
            }
        );

        var receipt = {
            gpc_detected: !!(window.klaroGeo && window.klaroGeo.gpc && window.klaroGeo.gpc.detected),
            gpc_affected_services: (window.klaroGeo && window.klaroGeo.gpc) ? window.klaroGeo.gpc.affectedServices : []
        };

        expect(receipt.gpc_detected).toBe(true);
        expect(receipt.gpc_affected_services).toEqual(['ads']);
    });

    test('should include empty GPC fields when GPC not detected', function() {
        runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            { services: [] }
        );

        var receipt = {
            gpc_detected: !!(window.klaroGeo && window.klaroGeo.gpc && window.klaroGeo.gpc.detected),
            gpc_affected_services: (window.klaroGeo && window.klaroGeo.gpc) ? window.klaroGeo.gpc.affectedServices : []
        };

        expect(receipt.gpc_detected).toBe(false);
        expect(receipt.gpc_affected_services).toEqual([]);
    });

    // =========================================================================
    // GPC with window.klaroGeo namespace
    // =========================================================================

    test('should set window.klaroGeo.gpc with correct structure', function() {
        navigator.globalPrivacyControl = true;
        runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            {
                services: [
                    { name: 'ad-storage', gpc_sensitive: true, default: true, required: false }
                ]
            }
        );

        expect(window.klaroGeo.gpc).toBeDefined();
        expect(window.klaroGeo.gpc.detected).toBe(true);
        expect(window.klaroGeo.gpc.enabled).toBe(true);
        expect(window.klaroGeo.gpc.affectedServices).toEqual(['ad-storage']);
    });

    test('should preserve existing klaroGeo properties', function() {
        window.klaroGeo.queue = [];
        window.klaroGeo.consentConfirmed = false;

        runGPCDetection(
            { enabled: true, gpc_purposes: ['advertising'] },
            { services: [] }
        );

        expect(window.klaroGeo.queue).toEqual([]);
        expect(window.klaroGeo.consentConfirmed).toBe(false);
        expect(window.klaroGeo.gpc).toBeDefined();
    });
});
