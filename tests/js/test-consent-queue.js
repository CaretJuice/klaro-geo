/**
 * Tests for Consent Queue functionality
 * @jest-environment jsdom
 */

describe('Consent Queue', () => {
    let originalDataLayer;

    beforeEach(() => {
        // Reset DOM and globals
        document.body.innerHTML = '';

        // Save original dataLayer
        originalDataLayer = window.dataLayer;

        // Reset all klaroGeo state
        delete window.klaroGeo;
        delete window.klaroConsentData;
        window.dataLayer = [];

        // Mock klaroGeoLog
        window.klaroGeoLog = jest.fn();
        window.klaroGeoWarn = jest.fn();
    });

    afterEach(() => {
        // Restore original dataLayer
        window.dataLayer = originalDataLayer;
        delete window.klaroGeo;
        delete window.klaroConsentData;
        jest.clearAllMocks();
    });

    /**
     * Helper to load klaro-geo.js fresh
     */
    function loadKlaroGeoJs() {
        // Clear any cached module
        jest.resetModules();

        // Load the script
        require('../../js/klaro-geo.js');
    }

    describe('Initialization', () => {
        test('should create klaroGeo namespace', () => {
            loadKlaroGeoJs();

            expect(window.klaroGeo).toBeDefined();
            expect(typeof window.klaroGeo.push).toBe('function');
            expect(Array.isArray(window.klaroGeo.queue)).toBe(true);
            expect(window.klaroGeo.consentConfirmed).toBe(false);
        });

        test('should preserve existing stub queue', () => {
            // Set up stub pattern before loading
            window.klaroGeo = window.klaroGeo || {};
            window.klaroGeo.push = window.klaroGeo.push || function(e) {
                (window.klaroGeo.queue = window.klaroGeo.queue || []).push(e);
            };

            // Queue some events via stub
            window.klaroGeo.push({ event: 'stub_event_1' });
            window.klaroGeo.push({ event: 'stub_event_2' });

            expect(window.klaroGeo.queue.length).toBe(2);

            // Now load the real script
            loadKlaroGeoJs();

            // Stub events should be preserved
            expect(window.klaroGeo.queue.length).toBe(2);
            expect(window.klaroGeo.queue[0].event).toBe('stub_event_1');
            expect(window.klaroGeo.queue[1].event).toBe('stub_event_2');
        });

        test('should detect GTM mode from klaroConsentData.gtmId', () => {
            // Set up GTM ID before loading
            window.klaroConsentData = { gtmId: 'GTM-XXXXX' };

            loadKlaroGeoJs();

            expect(window.klaroGeo.useGTM).toBe(true);
        });

        test('should detect non-GTM mode when gtmId is empty', () => {
            // Set up empty GTM ID
            window.klaroConsentData = { gtmId: '' };

            loadKlaroGeoJs();

            expect(window.klaroGeo.useGTM).toBe(false);
        });

        test('should detect non-GTM mode when klaroConsentData missing', () => {
            // No klaroConsentData at all
            loadKlaroGeoJs();

            expect(window.klaroGeo.useGTM).toBe(false);
        });

        test('should allow manual override of useGTM', () => {
            // Manually set useGTM before loading
            window.klaroGeo = { useGTM: false };
            window.klaroConsentData = { gtmId: 'GTM-XXXXX' };

            loadKlaroGeoJs();

            // Manual override should be preserved
            expect(window.klaroGeo.useGTM).toBe(false);
        });

        test('should detect consentModeType from klaroConsentData', () => {
            window.klaroConsentData = { gtmId: 'GTM-XXXXX', consentModeType: 'advanced' };

            loadKlaroGeoJs();

            expect(window.klaroGeo.consentModeType).toBe('advanced');
        });

        test('should default consentModeType to basic when not specified', () => {
            window.klaroConsentData = { gtmId: 'GTM-XXXXX' };

            loadKlaroGeoJs();

            expect(window.klaroGeo.consentModeType).toBe('basic');
        });

        test('should default consentModeType to basic when klaroConsentData missing', () => {
            loadKlaroGeoJs();

            expect(window.klaroGeo.consentModeType).toBe('basic');
        });

        test('should allow manual override of consentModeType', () => {
            window.klaroGeo = { consentModeType: 'advanced' };
            window.klaroConsentData = { gtmId: 'GTM-XXXXX', consentModeType: 'basic' };

            loadKlaroGeoJs();

            // Manual override should be preserved
            expect(window.klaroGeo.consentModeType).toBe('advanced');
        });
    });

    describe('Queuing Behavior', () => {
        beforeEach(() => {
            loadKlaroGeoJs();
        });

        test('should queue events when consent not confirmed', () => {
            window.klaroGeo.push({ event: 'test_event_1', data: 'value1' });
            window.klaroGeo.push({ event: 'test_event_2', data: 'value2' });

            expect(window.klaroGeo.queue.length).toBe(2);
            expect(window.klaroGeo.queue[0].event).toBe('test_event_1');
            expect(window.klaroGeo.queue[1].event).toBe('test_event_2');

            // Events should NOT be in dataLayer yet (only internal consent queue events)
            const testEvents = window.dataLayer.filter(e => e.event && e.event.startsWith('test_'));
            expect(testEvents.length).toBe(0);
        });

        test('should push events directly when consent already confirmed', () => {
            // Confirm consent manually
            window.klaroGeo.consentConfirmed = true;

            const initialDataLayerLength = window.dataLayer.length;

            window.klaroGeo.push({ event: 'direct_event', data: 'direct' });

            // Event should go directly to dataLayer
            expect(window.klaroGeo.queue.length).toBe(0);
            expect(window.dataLayer.length).toBe(initialDataLayerLength + 1);
            expect(window.dataLayer[window.dataLayer.length - 1].event).toBe('direct_event');
        });

        test('should enforce 100 event queue limit', () => {
            // Queue 101 events
            for (let i = 0; i < 101; i++) {
                window.klaroGeo.push({ event: `event_${i}` });
            }

            // Queue should be capped at 100
            expect(window.klaroGeo.queue.length).toBe(100);

            // First event should have been dropped (FIFO)
            expect(window.klaroGeo.queue[0].event).toBe('event_1');
            expect(window.klaroGeo.queue[99].event).toBe('event_100');
        });

        test('should warn when queue limit is exceeded', () => {
            const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});

            // Queue 101 events
            for (let i = 0; i < 101; i++) {
                window.klaroGeo.push({ event: `event_${i}` });
            }

            expect(warnSpy).toHaveBeenCalled();
            expect(warnSpy.mock.calls[0][0]).toContain('queue limit');

            warnSpy.mockRestore();
        });
    });

    describe('Consent Event Handling - GTM Mode', () => {
        beforeEach(() => {
            window.klaroConsentData = { gtmId: 'GTM-XXXXX' };
            loadKlaroGeoJs();
        });

        test('should flush queue on Klaro Consent Update event', () => {
            // Queue some events
            window.klaroGeo.push({ event: 'queued_event_1' });
            window.klaroGeo.push({ event: 'queued_event_2' });

            expect(window.klaroGeo.queue.length).toBe(2);
            expect(window.klaroGeo.consentConfirmed).toBe(false);

            // Simulate GTM template pushing Klaro Consent Update
            window.dataLayer.push({
                event: 'Klaro Consent Update',
                consentMode: { analytics_storage: 'granted' }
            });

            // Queue should be flushed
            expect(window.klaroGeo.queue.length).toBe(0);
            expect(window.klaroGeo.consentConfirmed).toBe(true);

            // Events should now be in dataLayer
            const queuedEvents = window.dataLayer.filter(e =>
                e.event === 'queued_event_1' || e.event === 'queued_event_2'
            );
            expect(queuedEvents.length).toBe(2);
        });

        test('should NOT flush on Klaro Consent Data in GTM mode', () => {
            window.klaroGeo.push({ event: 'queued_event' });

            // Push Klaro Consent Data (wrong event for GTM mode)
            window.dataLayer.push({
                event: 'Klaro Consent Data',
                consentMode: { analytics_storage: 'granted' }
            });

            // Queue should NOT be flushed
            expect(window.klaroGeo.queue.length).toBe(1);
            expect(window.klaroGeo.consentConfirmed).toBe(false);
        });

        test('should only process first consent event', () => {
            window.klaroGeo.push({ event: 'queued_event' });

            // First consent event
            window.dataLayer.push({ event: 'Klaro Consent Update' });

            expect(window.klaroGeo.consentConfirmed).toBe(true);
            expect(window.klaroGeo.queue.length).toBe(0);

            // Add another event and trigger consent again
            window.klaroGeo.push({ event: 'second_event' });
            window.dataLayer.push({ event: 'Klaro Consent Update' });

            // Second event should have gone directly (not queued)
            expect(window.klaroGeo.queue.length).toBe(0);

            // Both events should be in dataLayer
            const events = window.dataLayer.filter(e =>
                e.event === 'queued_event' || e.event === 'second_event'
            );
            expect(events.length).toBe(2);
        });
    });

    describe('Consent Event Handling - GTM Advanced Mode', () => {
        beforeEach(() => {
            window.klaroConsentData = { gtmId: 'GTM-XXXXX', consentModeType: 'advanced' };
            loadKlaroGeoJs();
        });

        test('should detect advanced consent mode type', () => {
            expect(window.klaroGeo.consentModeType).toBe('advanced');
        });

        test('should flush queue on Klaro Consent Update event in advanced mode', () => {
            // Queue some events
            window.klaroGeo.push({ event: 'queued_event_1' });
            window.klaroGeo.push({ event: 'queued_event_2' });

            expect(window.klaroGeo.queue.length).toBe(2);

            // Simulate GTM template pushing Klaro Consent Update
            window.dataLayer.push({
                event: 'Klaro Consent Update',
                consentMode: { analytics_storage: 'denied' }
            });

            // Queue should be flushed
            expect(window.klaroGeo.queue.length).toBe(0);
            expect(window.klaroGeo.consentConfirmed).toBe(true);
        });

        test('should still use Klaro Consent Update as flush trigger (not Klaro Consent Data)', () => {
            window.klaroGeo.push({ event: 'queued_event' });

            // Push Klaro Consent Data (wrong event for GTM mode, even in advanced)
            window.dataLayer.push({
                event: 'Klaro Consent Data',
                consentMode: { analytics_storage: 'denied' }
            });

            // Queue should NOT be flushed - still GTM mode
            expect(window.klaroGeo.queue.length).toBe(1);
            expect(window.klaroGeo.consentConfirmed).toBe(false);
        });
    });

    describe('Consent Event Handling - Non-GTM Mode', () => {
        beforeEach(() => {
            window.klaroConsentData = { gtmId: '' };
            loadKlaroGeoJs();
        });

        test('should flush queue on Klaro Consent Update event (unified trigger)', () => {
            // Queue some events
            window.klaroGeo.push({ event: 'queued_event_1' });
            window.klaroGeo.push({ event: 'queued_event_2' });

            expect(window.klaroGeo.queue.length).toBe(2);

            // Simulate plugin pushing Klaro Consent Update
            window.dataLayer.push({
                event: 'Klaro Consent Update',
                consentMode: { analytics_storage: 'granted' }
            });

            // Queue should be flushed
            expect(window.klaroGeo.queue.length).toBe(0);
            expect(window.klaroGeo.consentConfirmed).toBe(true);
        });

        test('should NOT flush on Klaro Consent Data (deprecated event)', () => {
            window.klaroGeo.push({ event: 'queued_event' });

            // Push Klaro Consent Data (no longer used as trigger)
            window.dataLayer.push({
                event: 'Klaro Consent Data',
                consentMode: { analytics_storage: 'granted' }
            });

            // Queue should NOT be flushed
            expect(window.klaroGeo.queue.length).toBe(1);
            expect(window.klaroGeo.consentConfirmed).toBe(false);
        });
    });

    describe('queueFlushed Event', () => {
        beforeEach(() => {
            loadKlaroGeoJs();
        });

        test('should push queueFlushed event after flushing queued events', () => {
            // Queue some events
            window.klaroGeo.push({ event: 'queued_event_1' });
            window.klaroGeo.push({ event: 'queued_event_2' });
            window.klaroGeo.push({ event: 'queued_event_3' });

            // Trigger consent (unified trigger)
            window.dataLayer.push({ event: 'Klaro Consent Update' });

            // Find the queueFlushed event in dataLayer
            const queueFlushedEvents = window.dataLayer.filter(e =>
                e.event === 'Klaro Event' && e.klaroEventName === 'queueFlushed'
            );

            expect(queueFlushedEvents.length).toBe(1);
            expect(queueFlushedEvents[0].queueSize).toBe(3);
            expect(queueFlushedEvents[0].eventSource).toBe('klaro-geo');
        });

        test('should NOT push queueFlushed event when queue is empty', () => {
            // Trigger consent with no events queued
            window.dataLayer.push({ event: 'Klaro Consent Update' });

            // Should not have a queueFlushed event
            const queueFlushedEvents = window.dataLayer.filter(e =>
                e.event === 'Klaro Event' && e.klaroEventName === 'queueFlushed'
            );

            expect(queueFlushedEvents.length).toBe(0);
        });

        test('queueFlushed should appear after all flushed events in dataLayer', () => {
            // Queue events
            window.klaroGeo.push({ event: 'first_event' });
            window.klaroGeo.push({ event: 'second_event' });

            // Trigger consent
            window.dataLayer.push({ event: 'Klaro Consent Update' });

            // Find positions in dataLayer
            let firstEventIdx = -1;
            let secondEventIdx = -1;
            let queueFlushedIdx = -1;

            for (let i = 0; i < window.dataLayer.length; i++) {
                if (window.dataLayer[i].event === 'first_event') firstEventIdx = i;
                if (window.dataLayer[i].event === 'second_event') secondEventIdx = i;
                if (window.dataLayer[i].klaroEventName === 'queueFlushed') queueFlushedIdx = i;
            }

            expect(firstEventIdx).toBeGreaterThan(-1);
            expect(secondEventIdx).toBeGreaterThan(-1);
            expect(queueFlushedIdx).toBeGreaterThan(-1);
            expect(queueFlushedIdx).toBeGreaterThan(firstEventIdx);
            expect(queueFlushedIdx).toBeGreaterThan(secondEventIdx);
        });
    });

    describe('Edge Cases', () => {
        beforeEach(() => {
            loadKlaroGeoJs();
        });

        test('should handle empty queue on consent event', () => {
            // No events queued, just trigger consent
            window.dataLayer.push({ event: 'Klaro Consent Update' });

            expect(window.klaroGeo.consentConfirmed).toBe(true);
            expect(window.klaroGeo.queue.length).toBe(0);
        });

        test('should handle non-object dataLayer pushes', () => {
            // These should not cause errors
            window.dataLayer.push('string value');
            window.dataLayer.push(123);
            window.dataLayer.push(null);
            window.dataLayer.push(undefined);

            expect(window.klaroGeo.consentConfirmed).toBe(false);
        });

        test('should handle events without event property', () => {
            window.klaroGeo.push({ data: 'no event property' });

            expect(window.klaroGeo.queue.length).toBe(1);
            expect(window.klaroGeo.queue[0].data).toBe('no event property');
        });
    });
});
