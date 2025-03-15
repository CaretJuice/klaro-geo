/**
 * Frontend functionality for Klaro Geo
 */
(function() {
    // Initialize Klaro when the DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Check if Klaro is loaded
        if (typeof window.klaro !== 'undefined') {
            // Initialize Klaro
            window.klaro.setup();
            
            // Add event listeners for opening the modal
            var openButtons = document.querySelectorAll('.open-klaro-modal');
            for (var i = 0; i < openButtons.length; i++) {
                openButtons[i].addEventListener('click', function(e) {
                    e.preventDefault();
                    window.klaro.show();
                });
            }
        } else {
            console.error('Klaro is not loaded. Please check your configuration.');
        }
    });
    
    // Handle consent changes
    window.addEventListener('klaro-consent-changed', function(e) {
        console.log('Consent changed:', e.detail);
        
        // Push consent data to dataLayer if enabled
        if (window.dataLayer && window.klaroConfig.enableConsentReceipts) {
            window.dataLayer.push({
                'event': 'klaro-consent-changed',
                'klaro-consents': e.detail.consents
            });
        }
    });
})();