/**
 * Klaro Geo Consent Button
 *
 * Implements a floating button to reopen the Klaro consent modal
 * and handles click events for elements with the open-klaro-modal class.
 */

(function($) {
    'use strict';

    // Define default settings
    var defaultSettings = {
        enableFloatingButton: false,
        floatingButtonText: 'Manage Consent',
        floatingButtonTheme: 'light',
        floatingButtonPosition: 'bottom-right',
        debug: false
    };

    // Helper function to merge objects (replacement for jQuery's $.extend if not available)
    function mergeObjects(target) {
        if (target === undefined || target === null) {
            target = {};
        }

        for (var i = 1; i < arguments.length; i++) {
            var source = arguments[i];
            if (source === undefined || source === null) {
                continue;
            }

            for (var key in source) {
                if (source.hasOwnProperty(key)) {
                    target[key] = source[key];
                }
            }
        }

        return target;
    }

    // Get settings from global object or use defaults
    function getSettings() {
        // Try to get settings from different possible locations
        var settings = typeof window.klaroGeo !== 'undefined' ? window.klaroGeo : null;

        // Debug: log what we found
        if (settings && settings.debug) {
            console.log('klaroGeo settings found:', settings);
        }

        // If settings are found, merge with defaults
        if (settings) {
            // Use $.extend if available, otherwise use our own mergeObjects function
            var mergedSettings = (typeof $.extend === 'function')
                ? $.extend({}, defaultSettings, settings)
                : mergeObjects({}, defaultSettings, settings);

            // Debug: log merged settings
            if (mergedSettings.debug) {
                console.log('klaroGeo merged settings:', mergedSettings);
            }

            return mergedSettings;
        }

        // Log warning and return defaults
        console.warn('klaroGeo settings not found, using defaults');
        return defaultSettings;
    }

    // Initialize the consent button functionality
    function init() {
        var settings = getSettings();

        if (settings.debug) {
            console.log('Initializing Klaro Geo Consent Button, version:', settings.version);
        }

        // Create and append floating button if enabled in settings
        if (settings.enableFloatingButton === true) {
            // Check if button already exists to avoid duplicates
            if ($('.klaro-floating-button').length === 0) {
                if (settings.debug) {
                    console.log('Creating floating button with settings:', {
                        text: settings.floatingButtonText,
                        theme: settings.floatingButtonTheme,
                        position: settings.floatingButtonPosition
                    });
                }
                createFloatingButton(settings);
            } else if (settings.debug) {
                console.log('Floating button already exists, skipping creation');
            }
        } else if (settings.debug) {
            console.log('Floating button is disabled in settings');
        }
    }

    // Function to check if Klaro is loaded
    function isKlaroLoaded() {
        return typeof window.klaro !== 'undefined' && typeof window.klaro.show === 'function';
    }

    // Initialize with Klaro check
    function initWithKlaroCheck() {
        var settings = getSettings();

        if (settings.debug) {
            console.log('Checking if Klaro is loaded:', isKlaroLoaded());
        }

        // If Klaro is loaded, initialize
        if (isKlaroLoaded()) {
            init();
        } else {
            // If not, wait and try again
            if (settings.debug) {
                console.log('Klaro not loaded yet, waiting...');
            }
            setTimeout(initWithKlaroCheck, 500);
        }
    }

    // Function to handle clicks on open-klaro-modal elements
    function handleKlaroModalClick(e) {
        e.preventDefault();

        // Show Klaro modal if available
        if (isKlaroLoaded()) {
            window.klaro.show();
        } else {
            console.error('Klaro is not initialized or show() function is missing.');
        }
    }

    // Start the initialization process
    $(document).ready(function() {
        // Register click handler for .open-klaro-modal elements
        $(document).on('click', '.open-klaro-modal', handleKlaroModalClick);

        // Then start checking for Klaro
        initWithKlaroCheck();
    });

    /**
     * Creates and appends the floating consent button to the page
     */
    function createFloatingButton(settings) {
        // Get settings
        var buttonText = settings.floatingButtonText;
        var buttonTheme = settings.floatingButtonTheme;
        var buttonPosition = settings.floatingButtonPosition;

        try {
            // Create button with jQuery
            var $button = $('<button>', {
                'class': 'klaro-floating-button open-klaro-modal klaro-theme-' + buttonTheme + ' klaro-position-' + buttonPosition,
                'text': buttonText,
                'aria-label': buttonText
            });

            // Append to body
            $('body').append($button);

            // Add button styles if not already added
            var hasStyles = false;
            try {
                hasStyles = $('#klaro-floating-button-styles').length > 0;
            } catch (e) {
                // If jQuery selector fails, check with native JS
                hasStyles = document.getElementById('klaro-floating-button-styles') !== null;
            }

            if (!hasStyles) {
                var css = `
                    .klaro-floating-button {
                        position: fixed;
                        z-index: 1000;
                        padding: 10px 15px;
                        border-radius: 4px;
                        cursor: pointer;
                        font-size: 14px;
                        border: none;
                        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.25);
                        transition: all 0.2s ease;
                    }

                    .klaro-floating-button:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
                    }

                    /* Button positions */
                    .klaro-position-bottom-right {
                        bottom: 20px;
                        right: 20px;
                    }

                    .klaro-position-bottom-left {
                        bottom: 20px;
                        left: 20px;
                    }

                    .klaro-position-top-right {
                        top: 20px;
                        right: 20px;
                    }

                    .klaro-position-top-left {
                        top: 20px;
                        left: 20px;
                    }

                    /* Button themes */
                    .klaro-theme-light {
                        background-color: #f1f1f1;
                        color: #333;
                    }

                    .klaro-theme-dark {
                        background-color: #333;
                        color: #fff;
                    }

                    .klaro-theme-blue {
                        background-color: #1a73e8;
                        color: #fff;
                    }

                    .klaro-theme-green {
                        background-color: #0f9d58;
                        color: #fff;
                    }
                `;

                try {
                    $('<style>', {
                        id: 'klaro-floating-button-styles',
                        type: 'text/css',
                        html: css
                    }).appendTo('head');
                } catch (e) {
                    // Fallback to native JS if jQuery fails
                    var style = document.createElement('style');
                    style.id = 'klaro-floating-button-styles';
                    style.type = 'text/css';
                    style.appendChild(document.createTextNode(css));
                    document.head.appendChild(style);
                }
            }
        } catch (e) {
            // If jQuery methods fail, fall back to native JS
            console.warn('jQuery methods failed, falling back to native JS:', e);

            // Create button with native JS
            var button = document.createElement('button');
            button.className = 'klaro-floating-button open-klaro-modal klaro-theme-' + buttonTheme + ' klaro-position-' + buttonPosition;
            button.textContent = buttonText;
            button.setAttribute('aria-label', buttonText);

            // Append to body
            document.body.appendChild(button);

            // Add button styles if not already added
            if (!document.getElementById('klaro-floating-button-styles')) {
                var css = `
                    .klaro-floating-button {
                        position: fixed;
                        z-index: 1000;
                        padding: 10px 15px;
                        border-radius: 4px;
                        cursor: pointer;
                        font-size: 14px;
                        border: none;
                        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.25);
                        transition: all 0.2s ease;
                    }

                    .klaro-floating-button:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
                    }

                    /* Button positions */
                    .klaro-position-bottom-right {
                        bottom: 20px;
                        right: 20px;
                    }

                    .klaro-position-bottom-left {
                        bottom: 20px;
                        left: 20px;
                    }

                    .klaro-position-top-right {
                        top: 20px;
                        right: 20px;
                    }

                    .klaro-position-top-left {
                        top: 20px;
                        left: 20px;
                    }

                    /* Button themes */
                    .klaro-theme-light {
                        background-color: #f1f1f1;
                        color: #333;
                    }

                    .klaro-theme-dark {
                        background-color: #333;
                        color: #fff;
                    }

                    .klaro-theme-blue {
                        background-color: #1a73e8;
                        color: #fff;
                    }

                    .klaro-theme-green {
                        background-color: #0f9d58;
                        color: #fff;
                    }
                `;

                var style = document.createElement('style');
                style.id = 'klaro-floating-button-styles';
                style.type = 'text/css';

                if (style.styleSheet) {
                    // For IE
                    style.styleSheet.cssText = css;
                } else {
                    // For modern browsers
                    style.appendChild(document.createTextNode(css));
                }

                document.head.appendChild(style);
            }
        }
    }

})(jQuery);