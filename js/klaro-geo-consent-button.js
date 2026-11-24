/**
 * Klaro Geo Consent Button
 *
 * Opens the Klaro consent modal when any element with the class
 * open-klaro-modal is clicked (the shortcode button, a menu item, a theme's
 * own link), and adds the floating button when it is enabled.
 *
 * Settings come from window.klaroGeo, which PHP prints before this script.
 * The script is deferred and depends on klaro.js, so the document is parsed
 * and Klaro is loaded when it runs. Styles are in css/klaro-consent-button.css.
 */
(function() {
    'use strict';

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.open-klaro-modal')) {
            return;
        }
        e.preventDefault();

        // Klaro is a separate library; a site can dequeue or block it.
        if (window.klaro && typeof window.klaro.show === 'function') {
            window.klaro.show();
        } else {
            klaroGeoError('Klaro is not initialized or show() function is missing.');
        }
    });

    var settings = window.klaroGeo;
    if (settings.enableFloatingButton !== true) {
        return;
    }

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'klaro-floating-button open-klaro-modal' +
        ' klaro-theme-' + settings.floatingButtonTheme +
        ' klaro-position-' + settings.floatingButtonPosition;
    button.textContent = settings.floatingButtonText;
    button.setAttribute('aria-label', settings.floatingButtonText);
    document.body.appendChild(button);
})();
