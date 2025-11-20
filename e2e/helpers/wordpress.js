/**
 * WordPress Helper for E2E Tests
 *
 * Provides utilities for interacting with WordPress in E2E tests
 */

class WordPressHelper {
  constructor(page) {
    this.page = page;
  }

  /**
   * Log in to WordPress admin
   * @param {string} username - WordPress username
   * @param {string} password - WordPress password
   */
  async login(username = 'admin', password = 'password') {
    await this.page.goto('/wp-login.php');

    // Fill in login form
    await this.page.fill('#user_login', username);
    await this.page.fill('#user_pass', password);

    // Submit form
    await this.page.click('#wp-submit');

    // Wait for redirect to admin
    await this.page.waitForURL('**/wp-admin/**', { timeout: 10000 });
  }

  /**
   * Check if currently logged in
   * @returns {Promise<boolean>}
   */
  async isLoggedIn() {
    const bodyClass = await this.page.getAttribute('body', 'class');
    return bodyClass && bodyClass.includes('logged-in');
  }

  /**
   * Navigate to plugin settings page
   * @param {string} page - Admin page slug
   */
  async gotoAdminPage(page) {
    await this.page.goto(`/wp-admin/admin.php?page=${page}`);
  }

  /**
   * Activate a plugin
   * @param {string} pluginFile - Plugin file path (e.g., 'klaro-geo/klaro-geo.php')
   */
  async activatePlugin(pluginFile) {
    await this.page.goto('/wp-admin/plugins.php');

    const activateLink = this.page.locator(`a[href*="plugin=${encodeURIComponent(pluginFile)}"][href*="action=activate"]`);

    if (await activateLink.isVisible()) {
      await activateLink.click();
      await this.page.waitForURL('**/wp-admin/plugins.php?**');
    }
  }

  /**
   * Deactivate a plugin
   * @param {string} pluginFile - Plugin file path
   */
  async deactivatePlugin(pluginFile) {
    await this.page.goto('/wp-admin/plugins.php');

    const deactivateLink = this.page.locator(`a[href*="plugin=${encodeURIComponent(pluginFile)}"][href*="action=deactivate"]`);

    if (await deactivateLink.isVisible()) {
      await deactivateLink.click();
      await this.page.waitForURL('**/wp-admin/plugins.php?**');
    }
  }

  /**
   * Get a WordPress option value
   * @param {string} optionName - Option name
   * @returns {Promise<any>}
   */
  async getOption(optionName) {
    return await this.page.evaluate(async (name) => {
      const formData = new FormData();
      formData.append('action', 'get_option');
      formData.append('option_name', name);

      const response = await fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });

      return await response.json();
    }, optionName);
  }

  /**
   * Clear WordPress cookies
   */
  async clearCookies() {
    await this.page.context().clearCookies();
  }

  /**
   * Wait for WordPress to be fully loaded
   */
  async waitForWordPress() {
    await this.page.waitForLoadState('domcontentloaded');
    await this.page.waitForFunction(() => {
      return document.readyState === 'complete';
    });
  }
}

module.exports = { WordPressHelper };
