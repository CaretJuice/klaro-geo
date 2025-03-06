/**
 * Klaro Consent Button
 * 
 * Handles the creation and functionality of consent buttons
 */

// Function to show the Klaro consent modal
function showKlaroModal() {
    console.log('Attempting to show Klaro modal');

    // Try multiple ways to show the Klaro modal
    if (typeof window.klaro !== 'undefined' && typeof window.klaro.show === 'function') {
        console.log('Using window.klaro.show()');
        window.klaro.show();
    } else if (typeof window.klaroManager !== 'undefined' && typeof window.klaroManager.show === 'function') {
        console.log('Using window.klaroManager.show()');
        window.klaroManager.show();
    } else if (typeof window.klaroApi !== 'undefined' && typeof window.klaroApi.openConsentManager === 'function') {
        console.log('Using window.klaroApi.openConsentManager()');
        window.klaroApi.openConsentManager();
    } else {
        console.error('Klaro is not loaded or available. Checking for config...');

        // If Klaro isn't loaded but config is, try to manually trigger it
        if (typeof window.klaroConfig !== 'undefined') {
            console.log('Config found, attempting to manually initialize Klaro');

            // Create a script element to reload Klaro
            const script = document.createElement('script');
            script.setAttribute('defer', '');
            script.setAttribute('data-config', 'klaroConfig');
            script.setAttribute('src', 'https://cdn.kiprotect.com/klaro/v' +
                (window.klaroVersion || '0.7') + '/klaro.js');

            // Add event listener to show the modal once loaded
            script.onload = function() {
                console.log('Klaro script loaded, attempting to show modal');
                if (typeof window.klaro !== 'undefined' && typeof window.klaro.show === 'function') {
                    window.klaro.show();
                }
            };

            document.body.appendChild(script);
        } else {
            console.error('Klaro config not found');
        }
    }
}

// Function to initialize the consent buttons
function initConsentButtons() {
    // Get all consent buttons and attach click event
    const consentButtons = document.querySelectorAll('.klaro-consent-button, .klaro-menu-consent-button');
    consentButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            showKlaroModal();
        });
    });

    // Create and append the floating button if enabled
    if (window.klaroConsentButtonData && window.klaroConsentButtonData.floatingButtonEnabled) {
        const floatingButton = document.createElement('button');
        floatingButton.className = 'klaro-consent-button ' + window.klaroConsentButtonData.theme;
        floatingButton.textContent = window.klaroConsentButtonData.buttonText;
        floatingButton.addEventListener('click', showKlaroModal);
        document.body.appendChild(floatingButton);
    }
}

// Initialize on DOMContentLoaded
document.addEventListener('DOMContentLoaded', initConsentButtons);

// Export functions for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        showKlaroModal,
        initConsentButtons
    };
}

// Auto-initialize in test environment
if (typeof jest !== 'undefined' && typeof window !== 'undefined' && window.klaroConsentButtonData) {
    initConsentButtons();
}