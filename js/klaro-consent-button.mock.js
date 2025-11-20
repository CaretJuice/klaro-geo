/**
 * Mock implementation of the Klaro consent button functionality
 */

/**
 * Handle click on elements with the open-klaro-modal class
 * 
 * @param {Event} e - The click event
 */
function handleKlaroModalClick(e) {
    // Prevent default link behavior
    e.preventDefault();
    
    // Check if Klaro is available and has a show method
    if (window.klaro && typeof window.klaro.show === 'function') {
        // Show the Klaro modal
        window.klaro.show();
    } else {
        console.error('Klaro is not initialized or show() function is missing.');
    }
}

/**
 * Initialize Klaro consent button functionality
 */
function initKlaroConsentButton() {
    // Find all elements with the open-klaro-modal class
    const buttons = document.querySelectorAll('.open-klaro-modal');
    
    // Add click event listener to each button
    buttons.forEach(button => {
        button.addEventListener('click', handleKlaroModalClick);
    });
}

// Initialize when the DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKlaroConsentButton);
} else {
    initKlaroConsentButton();
}

// Export for testing
module.exports = {
    handleKlaroModalClick,
    initKlaroConsentButton
};