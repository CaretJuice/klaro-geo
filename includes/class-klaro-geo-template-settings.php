<?php
/**
 * Klaro Geo Template Settings Class
 *
 * A class for handling template settings stored in WordPress options
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Include the base option class if not already included
if (!class_exists('Klaro_Geo_Option')) {
    require_once dirname(__FILE__) . '/class-klaro-geo-option.php';
}

/**
 * Class Klaro_Geo_Template_Settings
 * 
 * Class for handling template settings stored in WordPress options
 */
class Klaro_Geo_Template_Settings extends Klaro_Geo_Option {
    /**
     * Constructor
     *
     * @param string $option_name The option name in the WordPress database
     * @param array $default_value Default value if option doesn't exist
     */
    public function __construct($option_name = 'klaro_geo_templates', $default_value = array()) {
        parent::__construct($option_name, $default_value);

        // Debug log the initial value
        klaro_geo_debug_log('Template Settings constructor - Initial value: ' . (empty($this->value) ? 'empty' : 'not empty') . ' (count: ' . count($this->value) . ')');

        // If empty, initialize with default templates
        if (empty($this->value)) {
            klaro_geo_debug_log('Template Settings constructor - Initializing with default templates');
            $this->value = $this->get_default_templates();
            $this->is_modified = true;
        } else {
            // Log the templates that were loaded
            $template_names = array_map(function($template) {
                return isset($template['name']) ? $template['name'] : 'unnamed';
            }, $this->value);
            klaro_geo_debug_log('Template Settings constructor - Loaded templates: ' . implode(', ', $template_names));
        }
    }

    /**
     * Get default templates
     *
     * @return array The default templates
     */
    public function get_default_templates() {
        $default_templates = array(
            'default' => array(
                'name' => 'Default Template',
                'description' => 'The default template used when no location-specific template is found',
                'config' => array(
                    'default' => false,
                    'mustConsent' => false,
                    'acceptAll' => true,
                    'hideDeclineAll' => false,
                    'hideLearnMore' => false,
                    'noticeAsModal' => false,
                    'additionalClass' => '',
                    'disablePoweredBy' => false,
                    'htmlTexts' => true,
                    'embedded' => false,
                    'groupByPurpose' => true,
                    'cookieDomain' => '',
                    'cookieExpiresAfterDays' => 365,
                    'privacyPolicy' => array(
                        'default' => '/privacy-policy/'
                    ),
                    'translations' => array(
                        'zz' => array(
                            'privacyPolicyUrl' => '/privacy-policy/',
                            'consentModal' => array(
                                'title' => 'Information that we collect',
                                'description' => 'Here you can see and customize the information that we collect about you.'
                            ),
                            'consentNotice' => array(
                                'title' => 'Cookie Notice',
                                'changeDescription' => 'There were changes since your last visit, please update your consent.',
                                'description' => 'We use cookies to personalize content and analyze traffic to our website. You can choose to accept or decline these cookies.',
                                'learnMore' => 'Learn more'
                            ),
                            'ok' => 'Accept',
                            'decline' => 'Decline',
                            'acceptAll' => 'Accept all',
                            'acceptSelected' => 'Accept selected',
                            'close' => 'Close',
                            'purposes' => array(
                                'functional' => 'Functional',
                                'analytics' => 'Analytics',
                                'advertising' => 'Advertising',
                                'personalization' => 'Personalization'
                            ),
                            'purposeItem' => array(
                                'service' => 'service',
                                'services' => 'services'
                            )
                        )
                    )
                )
            ),
            'strict' => array(
                'name' => 'Strict Opt-In',
                'description' => 'Requires explicit consent for all services (opt-in)',
                'config' => array(
                    'default' => false,
                    'mustConsent' => true,
                    'acceptAll' => true,
                    'hideDeclineAll' => false,
                    'hideLearnMore' => false,
                    'noticeAsModal' => true
                )
            ),
            'relaxed' => array(
                'name' => 'Relaxed Opt-Out',
                'description' => 'Assumes consent for all services (opt-out)',
                'config' => array(
                    'default' => true,
                    'mustConsent' => false,
                    'acceptAll' => true,
                    'hideDeclineAll' => true,
                    'hideLearnMore' => false,
                    'noticeAsModal' => false
                )
            )
        );
        
        return $default_templates;
    }

    /**
     * Get a template
     *
     * @param string $template_key The template key
     * @return array|null The template or null if not found
     */
    public function get_template($template_key) {
        return $this->get_key($template_key);
    }

    /**
     * Set a template
     *
     * @param string $template_key The template key
     * @param array $template The template to set
     * @return $this For method chaining
     */
    public function set_template($template_key, $template) {
        return $this->set_key($template_key, $template);
    }

    /**
     * Remove a template
     *
     * @param string $template_key The template key
     * @return $this For method chaining
     */
    public function remove_template($template_key) {
        return $this->remove_key($template_key);
    }

    /**
     * Get a template's config
     *
     * @param string $template_key The template key
     * @return array|null The template config or null if not found
     */
    public function get_template_config($template_key) {
        $template = $this->get_template($template_key);
        
        if (!$template || !isset($template['config'])) {
            return null;
        }
        
        return $template['config'];
    }

    /**
     * Set a template's config
     *
     * @param string $template_key The template key
     * @param array $config The config to set
     * @return $this For method chaining
     */
    public function set_template_config($template_key, $config) {
        $template = $this->get_template($template_key);

        if (!$template) {
            $template = array(
                'name' => $template_key,
                'description' => '',
                'config' => $config
            );
        } else {
            // Check if we're losing translations
            if (isset($template['config']['translations']) &&
                (!isset($config['translations']) || empty($config['translations']))) {
                klaro_geo_debug_log('WARNING: Template ' . $template_key . ' is losing translations in set_template_config');

                // Preserve the existing translations
                $config['translations'] = $template['config']['translations'];
                klaro_geo_debug_log('Preserved existing translations for template ' . $template_key);
            }

            $template['config'] = $config;
        }

        return $this->set_template($template_key, $template);
    }

    /**
     * Check if a template has translations
     *
     * @param string $template_key The template key
     * @return bool Whether the template has translations
     */
    public function has_translations($template_key) {
        $template = $this->get_template($template_key);

        if (!$template ||
            !isset($template['config']) ||
            !isset($template['config']['translations'])) {
            return false;
        }

        return !empty($template['config']['translations']);
    }

    /**
     * Get a template's translation
     *
     * @param string $template_key The template key
     * @param string $language The language code
     * @return array|null The translation or null if not found
     */
    public function get_template_translation($template_key, $language) {
        $config = $this->get_template_config($template_key);
        
        if (!$config || !isset($config['translations']) || !isset($config['translations'][$language])) {
            return null;
        }
        
        return $config['translations'][$language];
    }

    /**
     * Set a template's translation
     *
     * @param string $template_key The template key
     * @param string $language The language code
     * @param array $translation The translation to set
     * @return $this For method chaining
     */
    public function set_template_translation($template_key, $language, $translation) {
        $config = $this->get_template_config($template_key);
        
        if (!$config) {
            return $this;
        }
        
        if (!isset($config['translations'])) {
            $config['translations'] = array();
        }
        
        $config['translations'][$language] = $translation;
        
        return $this->set_template_config($template_key, $config);
    }

    /**
     * Remove a template's translation
     *
     * @param string $template_key The template key
     * @param string $language The language code
     * @return $this For method chaining
     */
    public function remove_template_translation($template_key, $language) {
        $config = $this->get_template_config($template_key);
        
        if (!$config || !isset($config['translations']) || !isset($config['translations'][$language])) {
            return $this;
        }
        
        unset($config['translations'][$language]);
        
        return $this->set_template_config($template_key, $config);
    }

    /**
     * Update a template from form data
     *
     * @param string $template_key The template key
     * @param array $form_data The form data
     * @return $this For method chaining
     */
    public function update_template_from_form($template_key, $form_data) {
        if (!is_array($form_data)) {
            klaro_geo_debug_log('Invalid form data for template ' . $template_key);
            return $this;
        }
        
        $template = $this->get_template($template_key);
        
        if (!$template) {
            $template = array(
                'name' => isset($form_data['name']) ? $form_data['name'] : $template_key,
                'description' => isset($form_data['description']) ? $form_data['description'] : '',
                'config' => array()
            );
        }
        
        // Update template name and description
        if (isset($form_data['name'])) {
            $template['name'] = $form_data['name'];
        }
        
        if (isset($form_data['description'])) {
            $template['description'] = $form_data['description'];
        }
        
        // Update template config
        if (isset($form_data['config']) && is_array($form_data['config'])) {
            $template['config'] = array_merge($template['config'], $form_data['config']);
        }
        
        // Handle translations
        if (isset($form_data['translations']) && is_array($form_data['translations'])) {
            if (!isset($template['config']['translations'])) {
                $template['config']['translations'] = array();
            }
            
            foreach ($form_data['translations'] as $language => $translation) {
                $template['config']['translations'][$language] = $translation;
            }
        }
        
        // Handle translations_json
        if (isset($form_data['translations_json'])) {
            $template['config']['translations_json'] = $form_data['translations_json'];
            
            // Try to decode the JSON and use it if valid
            $decoded = json_decode($form_data['translations_json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $template['config']['translations'] = $decoded;
            }
        }
        
        return $this->set_template($template_key, $template);
    }
}