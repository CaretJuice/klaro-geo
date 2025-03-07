/**
 * Mock version of the Klaro consent button for testing
 */

// Export the click handler function directly
module.exports = {
    handleKlaroModalClick: function(e) {
        e.preventDefault();
        
        // Check if Klaro is available
        if (typeof window.klaro !== 'undefined' && typeof window.klaro.show === 'function') {
            window.klaro.show();
        } else {
            console.error('Klaro is not initialized or show() function is missing.');
        }
        
        return false;
    }
};