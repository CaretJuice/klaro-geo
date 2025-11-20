/**
 * Klaro Geo Debug Logging Utility
 *
 * Centralized debug logging that respects the plugin's debug settings.
 * Only outputs to console when debug logging is enabled in WordPress admin.
 */

(function() {
    'use strict';

    /**
     * Check if debug logging is enabled
     * @returns {boolean}
     */
    function isDebugEnabled() {
        // Check if klaroGeo object exists and has debugEnabled flag
        if (typeof window.klaroGeo !== 'undefined' &&
            typeof window.klaroGeo.debugEnabled !== 'undefined') {
            return window.klaroGeo.debugEnabled === true;
        }

        // Default to false if not set
        return false;
    }

    /**
     * Main debug logging function
     * @param {string} level - Log level: 'log', 'warn', 'info', 'error'
     * @param {...*} args - Arguments to log
     */
    function klaroGeoDebugLog(level) {
        // Convert arguments to array, excluding the first argument (level)
        var args = Array.prototype.slice.call(arguments, 1);

        // Always show errors, regardless of debug setting
        if (level === 'error') {
            console.error.apply(console, ['[Klaro Geo]'].concat(args));
            return;
        }

        // For other levels, check if debug is enabled
        if (!isDebugEnabled()) {
            return;
        }

        // Prepend prefix to all messages
        var prefix = '[Klaro Geo]';

        // Log based on level
        switch(level) {
            case 'warn':
                console.warn.apply(console, [prefix].concat(args));
                break;
            case 'info':
                console.info.apply(console, [prefix].concat(args));
                break;
            case 'log':
            default:
                console.log.apply(console, [prefix].concat(args));
                break;
        }
    }

    // Export the main function and convenience wrappers
    window.klaroGeoDebugLog = klaroGeoDebugLog;

    // Convenience functions for each log level
    window.klaroGeoLog = function() {
        var args = Array.prototype.slice.call(arguments);
        klaroGeoDebugLog.apply(null, ['log'].concat(args));
    };

    window.klaroGeoWarn = function() {
        var args = Array.prototype.slice.call(arguments);
        klaroGeoDebugLog.apply(null, ['warn'].concat(args));
    };

    window.klaroGeoInfo = function() {
        var args = Array.prototype.slice.call(arguments);
        klaroGeoDebugLog.apply(null, ['info'].concat(args));
    };

    window.klaroGeoError = function() {
        var args = Array.prototype.slice.call(arguments);
        klaroGeoDebugLog.apply(null, ['error'].concat(args));
    };

})();
