/**
 * Klaro Consent Button Frontend JavaScript
 * Handles the frontend functionality of the Klaro consent button in menus
 */
(function() {
    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        // Find all consent buttons in menus
        var consentButtons = document.querySelectorAll('.klaro-menu-consent-button');
        
        // Add click event listener to each button
        consentButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Check if Klaro is available
                if (typeof window.klaro !== 'undefined') {
                    // Open the consent manager
                    window.klaro.show();
                } else {
                    console.error('Klaro is not available. Make sure it is properly loaded.');
                }
            });
        });
    });
})();