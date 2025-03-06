/**
 * Klaro Consent Button
 * 
 * Handles the creation and functionality of consent buttons
 */

// Function to show the Klaro consent modal
function showKlaroModal() {
    if (typeof window.klaro !== 'undefined') {
        window.klaro.show();
    } else {
        console.error('Klaro is not loaded or available');
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