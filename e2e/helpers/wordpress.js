/**
 * WordPress Helper for E2E Tests
 *
 * Provides utilities for interacting with WordPress in E2E tests
 */

const { execSync } = require('child_process');

// Docker container name for E2E tests
const DOCKER_CONTAINER = 'klaro-geo-wordpress-e2e';

/**
 * Execute a WP-CLI command inside the Docker container
 * @param {string} command - WP-CLI command (without 'wp' prefix)
 * @param {Object} options - Options
 * @param {string} options.stdin - Optional stdin input
 * @returns {string} - Command output
 */
function wpCli(command, options = {}) {
  const { timeout = 10000, stdin } = options;
  const fullCommand = `docker exec ${stdin ? '-i' : ''} ${DOCKER_CONTAINER} wp ${command} --allow-root`;

  try {
    const output = execSync(fullCommand, {
      encoding: 'utf-8',
      timeout,
      input: stdin,
      stdio: stdin ? ['pipe', 'pipe', 'pipe'] : ['pipe', 'pipe', 'pipe']
    });
    return output.trim();
  } catch (error) {
    // Return stderr or error message
    if (error.stderr) {
      throw new Error(`WP-CLI error: ${error.stderr}`);
    }
    throw error;
  }
}

/**
 * Update Klaro service settings via WP-CLI (fast, no browser needed)
 * Uses wp eval to update the option directly via PHP
 * Also regenerates the klaro-config.js file
 * @param {string} serviceName - Service name (e.g., 'google-tag-manager')
 * @param {Object} settings - Settings to update (e.g., { required: false, default: false })
 * @returns {Object} - Result of the update
 */
function updateServiceSettingsViaCli(serviceName, settings) {
  try {
    // Build simple PHP code - avoid complex escaping by using base64
    const settingsJson = JSON.stringify(settings);
    const settingsBase64 = Buffer.from(settingsJson).toString('base64');

    // PHP code that decodes base64 settings and regenerates config file
    const phpCode = [
      '$services = get_option("klaro_geo_services");',
      'if (is_string($services)) $services = json_decode($services, true);',
      'if (!is_array($services)) { echo "ERROR: not array"; exit(1); }',
      '$found = false;',
      'foreach ($services as &$s) {',
      `  if ($s["name"] === "${serviceName}") {`,
      `    $new = json_decode(base64_decode("${settingsBase64}"), true);`,
      '    foreach ($new as $k => $v) $s[$k] = $v;',
      '    $found = true;',
      '  }',
      '}',
      'if (!$found) { echo "ERROR: not found"; exit(1); }',
      'update_option("klaro_geo_services", $services);',
      // Clear the service settings cache so the new values are used
      'if (class_exists("Klaro_Geo_Service_Settings")) { Klaro_Geo_Service_Settings::clear_instance_cache(); }',
      // Regenerate the klaro-config.js file
      'if (function_exists("klaro_geo_generate_config_file")) { klaro_geo_generate_config_file(); }',
      'echo "SUCCESS";'
    ].join(' ');

    const result = wpCli(`eval '${phpCode}'`);

    if (result.includes('ERROR:')) {
      return { success: false, error: result };
    }

    return { success: true };
  } catch (error) {
    return { success: false, error: error.message };
  }
}

/**
 * Reset a Klaro service to default settings via WP-CLI
 * These defaults match the plugin's klaro-geo-defaults.php
 * @param {string} serviceName - Service name to reset
 * @returns {Object} - Result of the reset
 */
function resetServiceToDefaultsViaCli(serviceName) {
  // Plugin defaults: all services are opt-in (required:false, default:false)
  const defaults = {
    'google-tag-manager': { required: false, default: false },
    'google-analytics': { required: false, default: false },
    'google-ads': { required: false, default: false },
  };

  const settings = defaults[serviceName];
  if (!settings) {
    return { success: false, error: `No defaults defined for ${serviceName}` };
  }

  return updateServiceSettingsViaCli(serviceName, settings);
}

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

  /**
   * Update a Klaro service's settings
   * Must be logged in and on the services admin page
   * @param {string} serviceName - Service name (e.g., 'google-tag-manager')
   * @param {Object} settings - Settings to update (e.g., { required: false, default: false })
   * @returns {Promise<Object>} - Result of the update
   */
  async updateServiceSettings(serviceName, settings) {
    // Navigate to services page if not already there
    const currentUrl = this.page.url();
    if (!currentUrl.includes('klaro-geo-services')) {
      await this.gotoAdminPage('klaro-geo-services');
      await this.page.waitForLoadState('networkidle');
    }

    return await this.page.evaluate(async ({ serviceName, settings }) => {
      if (typeof klaroGeoServices === 'undefined') {
        return { success: false, error: 'klaroGeoServices not defined' };
      }

      const services = klaroGeoServices.services || [];
      const serviceIndex = services.findIndex(s => s.name === serviceName);

      if (serviceIndex === -1) {
        return { success: false, error: `Service ${serviceName} not found` };
      }

      // Update the service with new settings
      Object.assign(services[serviceIndex], settings);

      // Save via AJAX
      try {
        const response = await fetch('/wp-admin/admin-ajax.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'save_klaro_services',
            services: JSON.stringify(services),
            _wpnonce: klaroGeoServices.nonce
          }),
          credentials: 'same-origin'
        });

        const result = await response.json();
        return result;
      } catch (e) {
        return { success: false, error: e.message };
      }
    }, { serviceName, settings });
  }

  /**
   * Reset a Klaro service to default settings
   * @param {string} serviceName - Service name to reset
   * @param {Object} defaultSettings - Default settings for the service
   * @returns {Promise<Object>} - Result of the reset
   */
  async resetServiceToDefaults(serviceName, defaultSettings = {}) {
    // Default settings for GTM
    const defaults = {
      'google-tag-manager': { required: true, default: true },
      'google-analytics': { required: false, default: false },
      'google-ads': { required: false, default: false },
      ...defaultSettings
    };

    const settings = defaults[serviceName] || {};
    return await this.updateServiceSettings(serviceName, settings);
  }
}

module.exports = {
  WordPressHelper,
  wpCli,
  updateServiceSettingsViaCli,
  resetServiceToDefaultsViaCli
};
