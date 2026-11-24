/**
 * Klaro Geo logging. Every script in the plugin logs through these; nothing
 * else calls console.* (bin/check-banned-patterns.sh enforces it).
 *
 *   klaroGeoLog / klaroGeoWarn   Shown only when debug logging is on
 *                                (window.klaroGeo.debugEnabled, set from the
 *                                plugin's "Enable debug logging" setting).
 *   klaroGeoError                Always shown.
 *
 * All three prefix the message with [Klaro Geo]. Do not type a prefix into
 * the message.
 */
(function() {
    'use strict';

    var PREFIX = '[Klaro Geo]';

    function write(method, args) {
        console[method].apply(console, [PREFIX].concat(Array.prototype.slice.call(args)));
    }

    function debugEnabled() {
        return !!(window.klaroGeo && window.klaroGeo.debugEnabled === true);
    }

    window.klaroGeoLog = function() {
        if (debugEnabled()) {
            write('log', arguments);
        }
    };

    window.klaroGeoWarn = function() {
        if (debugEnabled()) {
            write('warn', arguments);
        }
    };

    window.klaroGeoError = function() {
        write('error', arguments);
    };
})();
