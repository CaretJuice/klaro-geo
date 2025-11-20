/**
 * Klaro Helper for E2E Tests
 *
 * Provides utilities for interacting with Klaro consent manager in E2E tests
 */

class KlaroHelper {
  constructor(page) {
    this.page = page;
  }

  /**
   * Wait for Klaro library to be loaded
   * @param {number} timeout - Timeout in milliseconds
   */
  async waitForKlaroLoad(timeout = 10000) {
    await this.page.waitForFunction(() => {
      return typeof window.klaro !== 'undefined' &&
             typeof window.klaro.getManager === 'function';
    }, { timeout });
  }

  /**
   * Wait for Klaro modal to appear
   * @param {number} timeout - Timeout in milliseconds
   */
  async waitForModal(timeout = 10000) {
    // Klaro v0.7 uses either .cookie-notice for initial notice or .cm-modal for full modal
    await this.page.waitForSelector('.cookie-notice, .cm-modal', {
      state: 'visible',
      timeout
    });
  }

  /**
   * Check if Klaro modal is visible
   * @returns {Promise<boolean>}
   */
  async isModalVisible() {
    const modal = this.page.locator('.cookie-notice, .cm-modal');
    return await modal.isVisible().catch(() => false);
  }

  /**
   * Click "Accept All" button
   */
  async acceptAll() {
    await this.waitForModal();
    // Klaro v0.7 uses .cm-btn-success for accept/OK button
    await this.page.click('.cm-btn-success, button:has-text("OK"), button:has-text("That\'s ok")');

    // Wait for modal to close
    await this.page.waitForSelector('.cookie-notice, .cm-modal', {
      state: 'hidden',
      timeout: 5000
    });
  }

  /**
   * Click "Decline All" button
   * Note: Decline button may take 5-6 seconds to appear on initial load
   */
  async declineAll() {
    await this.waitForModal();
    // Klaro v0.7 uses .cn-decline or .cm-btn-danger for decline button
    // Wait up to 10 seconds for the decline button to be available
    await this.page.click('.cn-decline, .cm-btn-danger, button:has-text("Decline")', {
      timeout: 10000
    });

    // Wait for modal to close
    await this.page.waitForSelector('.cookie-notice, .cm-modal', {
      state: 'hidden',
      timeout: 5000
    });
  }

  /**
   * Toggle a specific service consent
   * @param {string} serviceName - Service name (e.g., 'google-analytics')
   * Note: Service must be visible. Services grouped by purpose may be nested inside a collapsed purpose item.
   */
  async toggleService(serviceName) {
    await this.waitForModal();

    // Service checkbox ID format: service-item-{serviceName}
    const checkbox = this.page.locator(`input#service-item-${serviceName}`);

    // Wait for checkbox to be available (may be nested inside collapsed purpose)
    await checkbox.waitFor({ state: 'attached', timeout: 5000 });
    await checkbox.click();
  }

  /**
   * Click "Save" button to save custom choices
   */
  async saveChoices() {
    // Klaro v0.7 uses .cm-btn-accept for the save button
    await this.page.click('.cm-btn-accept, button:has-text("Save")');

    // Wait for modal to close
    await this.page.waitForSelector('.cookie-notice, .cm-modal', {
      state: 'hidden',
      timeout: 5000
    });
  }

  /**
   * Get current consent state
   * @returns {Promise<Object>} Consent state object
   */
  async getConsents() {
    return await this.page.evaluate(() => {
      const manager = window.klaro?.getManager();
      return manager ? manager.consents : null;
    });
  }

  /**
   * Get consent data from cookies
   * @returns {Promise<Object|null>}
   */
  async getCookieConsent() {
    return await this.page.evaluate(() => {
      const cookies = document.cookie.split(';');
      for (let cookie of cookies) {
        const [name, value] = cookie.trim().split('=');
        if (name === 'klaro') {
          try {
            // Cookie format: {"google-tag-manager":true,"google-analytics":false}
            const services = JSON.parse(decodeURIComponent(value));
            // Return in a format compatible with tests (wrapped in services property)
            return { services: services };
          } catch (e) {
            return null;
          }
        }
      }
      return null;
    });
  }

  /**
   * Get consent data from localStorage or cookies (fallback)
   * @returns {Promise<Object|null>}
   */
  async getLocalStorageConsent() {
    // First try localStorage
    let data = await this.page.evaluate(() => {
      const data = localStorage.getItem('klaro');
      return data ? JSON.parse(data) : null;
    });

    // If not in localStorage, try cookies (Klaro default)
    if (!data) {
      data = await this.getCookieConsent();
    }

    return data;
  }

  /**
   * Clear consent data from localStorage and cookies
   */
  async clearConsent() {
    try {
      await this.page.evaluate(() => {
        // Clear localStorage
        localStorage.removeItem('klaro');
        localStorage.removeItem('klaro_consent_receipts');

        // Clear cookies (Klaro uses cookies by default)
        document.cookie = 'klaro=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
      });
    } catch (error) {
      // Ignore SecurityError or insecure operation errors if no page is loaded yet
      if (!error.message.includes('SecurityError') && !error.message.includes('insecure')) {
        throw error;
      }
    }
  }

  /**
   * Get dataLayer events
   * @returns {Promise<Array>}
   */
  async getDataLayerEvents() {
    return await this.page.evaluate(() => {
      return window.dataLayer || [];
    });
  }

  /**
   * Find specific event in dataLayer by klaroEventName
   * @param {string} eventName - Event name to find
   * @returns {Promise<Object|undefined>}
   */
  async findDataLayerEvent(eventName) {
    const dataLayer = await this.getDataLayerEvents();
    return dataLayer.find(event => event.klaroEventName === eventName);
  }

  /**
   * Find specific event in dataLayer by event property
   * @param {string} eventName - Event name to find (matches event property)
   * @returns {Promise<Object|undefined>}
   */
  async findDataLayerEventByName(eventName) {
    const dataLayer = await this.getDataLayerEvents();
    return dataLayer.find(event => event.event === eventName);
  }

  /**
   * Wait for specific dataLayer event by event property
   * @param {string} eventName - Event name to wait for (matches event property)
   * @param {number} timeout - Timeout in milliseconds
   * @returns {Promise<Object|undefined>} The event object if found
   */
  async waitForDataLayerEventByName(eventName, timeout = 5000) {
    await this.page.waitForFunction((name) => {
      return window.dataLayer && window.dataLayer.some(event =>
        event.event === name
      );
    }, eventName, { timeout });

    // Return the actual event
    return await this.findDataLayerEventByName(eventName);
  }

  /**
   * Wait for specific dataLayer event
   * @param {string} eventName - Event name to wait for
   * @param {number} timeout - Timeout in milliseconds
   * @returns {Promise<Object|undefined>} The event object if found
   */
  async waitForDataLayerEvent(eventName, timeout = 5000) {
    await this.page.waitForFunction((name) => {
      return window.dataLayer && window.dataLayer.some(event =>
        event.klaroEventName === name
      );
    }, eventName, { timeout });

    // Return the actual event
    return await this.findDataLayerEvent(eventName);
  }

  /**
   * Get consent receipts from localStorage
   * @returns {Promise<Array|null>}
   */
  async getConsentReceipts() {
    return await this.page.evaluate(() => {
      try {
        const data = localStorage.getItem('klaro_consent_receipts');
        return data ? JSON.parse(data) : null;
      } catch (e) {
        return null;
      }
    });
  }

  /**
   * Open Klaro modal programmatically
   */
  async openModal() {
    await this.page.evaluate(() => {
      if (window.klaro && window.klaro.show) {
        window.klaro.show();
      }
    });

    await this.waitForModal();
  }

  /**
   * Check if floating consent button is visible
   * @returns {Promise<boolean>}
   */
  async isFloatingButtonVisible() {
    const button = this.page.locator('.klaro-floating-button, #klaro-floating-button');
    return await button.isVisible().catch(() => false);
  }

  /**
   * Click floating consent button
   */
  async clickFloatingButton() {
    await this.page.click('.klaro-floating-button, #klaro-floating-button');
    await this.waitForModal();
  }
}

module.exports = { KlaroHelper };
