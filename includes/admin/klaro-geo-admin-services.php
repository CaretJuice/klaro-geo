<?php

// Services Page Content

function klaro_geo_services_page_content() {
    $purposes = explode(',', get_option('klaro_geo_purposes', 'functional,analytics,advertising')); // Get available purposes from settings

    // Initialize the service settings class
    $service_settings = new Klaro_Geo_Service_Settings();
    $services = $service_settings->get();
    klaro_geo_debug_log('Services page content - services: ' . print_r($services, true));

    // Scripts are now enqueued in klaro-geo-admin-scripts.php

    // Get templates
    $template_settings = new Klaro_Geo_Template_Settings();
    $templates = $template_settings->get();

    // Also make templates available globally with a unique timestamp to prevent caching issues
    wp_add_inline_script(
        'klaro-geo-services-js',
        'window.klaroTemplates = ' . wp_json_encode($templates) . ';' .
        'window.klaroTemplatesTimestamp = ' . time() . ';',
        'before'
    );
    ?>
    <div class="wrap">
        <h1>Klaro Services</h1>
        <?php // 3. Add Service Button ?>
        <button id="add-new-service" class="button button-primary">Add New Service</button>
        <?php // 4. Table for Existing Services ?>
        <table class="wp-list-table widefat fixed striped" id="klaro-services-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Required</th>
                    <th>Default</th>
                    <th>Purposes</th>
                    <th>Advanced</th>
                    <th>Cookies</th>
                    <th>Callbacks</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="klaro-services-list">
                 <?php
                if (empty($services)) {

                    echo "<tr><td colspan='8'>No services configured yet.</td></tr>"; // Updated colspan for the new advanced column
                } else {
                    foreach ($services as $index => $service) {
                        klaro_geo_debug_log('Processing service in admin loop: ' . print_r($service, true));

                        $name = isset($service['name']) ? esc_html($service['name']) : "N/A";
                        $required = isset($service['required']) ? ($service['required'] ? 'Yes' : 'No') : "N/A";
                        $default = isset($service['default']) ? ($service['default'] ? 'Yes' : 'No') : "N/A";
                        
                        // Handle purposes (ensure it's an array)
                        $purposes = isset($service['purposes']) ? $service['purposes'] : array();
                        $purposes_str = !empty($purposes) && is_array($purposes) ? esc_html(implode(', ', $purposes)) : "N/A";

                        // Handle advanced settings
                        $advanced = array();
                        if (isset($service['optOut']) && $service['optOut']) $advanced[] = 'Opt-Out';
                        if (isset($service['onlyOnce']) && $service['onlyOnce']) $advanced[] = 'Only Once';
                        if (isset($service['contextualConsentOnly']) && $service['contextualConsentOnly']) $advanced[] = 'Contextual Only';
                        $advanced_str = !empty($advanced) ? implode(', ', $advanced) : 'None';

                        // Handle cookies
                        $cookies = isset($service['cookies']) ? $service['cookies'] : array();
                        $cookies_str = !empty($cookies) && is_array($cookies) ? 
                            esc_html(json_encode($cookies)) : "N/A";
                        // Check for callbacks
                        $has_oninit = !empty($service['onInit']);
                        $has_onaccept = !empty($service['onAccept']);
                        $has_ondecline = !empty($service['onDecline']);
                        $callbacks = [];
                        if ($has_oninit) $callbacks[] = 'onInit';
                        if ($has_onaccept) $callbacks[] = 'onAccept';
                        if ($has_ondecline) $callbacks[] = 'onDecline';
                        $callbacks_str = !empty($callbacks) ? implode(', ', $callbacks) : 'None';

                        echo "<tr>";
                        echo "<td>" . $name . "</td>"; //Corrected to $name
                        echo "<td>" . $required . "</td>";
                        echo "<td>" . $default . "</td>";
                        echo "<td>" . $purposes_str . "</td>";
                        echo "<td>" . $advanced_str . "</td>";
                        echo "<td>" . $cookies_str . "</td>";
                        echo "<td>" . $callbacks_str . "</td>";
                        echo "<td>";
                        echo "<button class='edit-service button button-secondary' data-index='$index'>Edit</button> "; // Edit button
                        echo "<button class='delete-service button button-danger' data-index='$index'>Delete</button>"; // Delete button
                        echo "</td>";
                        echo "</tr>";
                    }
                }
                ?>



            </tbody>
        </table>



        <?php // 5. Form for Add/Edit Service (Hidden initially) ?>
        <div id="service-form-container" style="display: none;">
            <h2>Add/Edit Service</h2>
            <form id="service-form"> <input type="hidden" name="action" value="save_klaro_services">
                <input type="hidden" name="service_index" id="service_index">
                <label for="service_name">Name:</label><br>
                <input type="text" id="service_name" name="service_name" required><br>
                <label for="service_required">Required:</label><br>
                <select id="service_required" name="service_required">
                    <option value="global">Use Global Setting</option>
                    <option value="true">Yes</option>
                    <option value="false">No</option>
                </select>
                <p class="description">When set to "Yes", users cannot decline this service. Only use for essential services that are required for your website to function.</p><br>

                <label for="service_default">Default:</label><br>
                <select id="service_default" name="service_default">
                    <option value="global">Use Global Setting</option>
                    <option value="true">Yes</option>
                    <option value="false">No</option>
                </select>
                <p class="description">When set to "Yes", this service will be activated by default (opt-out). Use with caution as this may not be compliant with privacy regulations in some regions.</p><br>
                <label for="service_purposes">Purposes:</label><br>
                <div id="service_purposes_container"></div>
                <label for="service_cookies">Cookies:</label><br>
                <div id="service_cookies_container0"></div>

                <h3>Advanced Settings</h3>
                <div class="advanced-settings">
                    <label for="service_optout">
                        <input type="checkbox" id="service_optout" name="service_optout" value="1">
                        Opt-Out
                    </label>
                    <p class="description">If enabled, this service will be loaded even before the user gives explicit consent. <strong>We strongly advise against this</strong> as it may violate privacy regulations.</p>

                    <label for="service_onlyonce">
                        <input type="checkbox" id="service_onlyonce" name="service_onlyonce" value="1">
                        Only Once
                    </label>
                    <p class="description">If enabled, the service will only be executed once regardless of how often the user toggles it on and off. This is useful for tracking scripts that would generate new page view events every time they are re-enabled.</p>

                    <label for="service_contextual">
                        <input type="checkbox" id="service_contextual" name="service_contextual" value="1">
                        Contextual Consent Only
                    </label>
                    <p class="description">If enabled, this service will only be shown in the consent modal when it's actually used on the page (e.g., embedded YouTube videos).</p>
                </div>

                <h3>Callback Scripts</h3>
                <div class="callback-scripts">
                    <label for="service_oninit">onInit Script:</label><br>
                    <textarea id="service_oninit" name="service_oninit" rows="4" cols="50"></textarea>
                    <p class="description">JavaScript to execute when Klaro initializes this service.</p>

                    <label for="service_onaccept">onAccept Script:</label><br>
                    <textarea id="service_onaccept" name="service_onaccept" rows="4" cols="50"></textarea>
                    <p class="description">JavaScript to execute when the user accepts this service.</p>

                    <label for="service_ondecline">onDecline Script:</label><br>
                    <textarea id="service_ondecline" name="service_ondecline" rows="4" cols="50"></textarea>
                    <p class="description">JavaScript to execute when the user declines this service.</p>
                </div>

                <h3>Service Translations</h3>
                <div class="translations">
                    <p class="description">Configure translations for this service. The "zz" language code is used as a fallback for any missing translations.</p>
                    <p class="description"><strong>Note:</strong> Languages are automatically pulled from the Templates section. To add new languages, please add them in the Templates page first.</p>

                    <div id="service-translations-tabs" class="translations-container">
                        <ul class="translations-tabs-nav">
                            <li><a href="#service-tab-zz">Fallback</a></li>
                            <!-- Additional language tabs will be dynamically added based on templates -->
                        </ul>

                        <div id="service-tab-zz" class="translation-tab">
                            <h4>Fallback Translations (zz)</h4>
                            <p class="description">These translations will be used when a specific language translation is not available.</p>

                            <table class="form-table">
                                <tr>
                                    <th scope="row">Title</th>
                                    <td>
                                        <input type="text" id="service_translations_zz_title" name="service_translations[zz][title]" class="regular-text">
                                        <p class="description">The name of the service as displayed to users.</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Description</th>
                                    <td>
                                        <textarea id="service_translations_zz_description" name="service_translations[zz][description]" rows="4" cols="50" class="large-text"></textarea>
                                        <p class="description">A description of what this service does and what data it collects.</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" colspan="2"><h4>Opt-Out Message</h4></th>
                                </tr>

                                <tr>
                                    <th scope="row">Title</th>
                                    <td>
                                        <input type="text" id="service_translations_zz_optOut_title" name="service_translations[zz][optOut][title]" value="(opt-out)" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Description</th>
                                    <td>
                                        <input type="text" id="service_translations_zz_optOut_description" name="service_translations[zz][optOut][description]" value="This services is loaded by default (but you can opt out)" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" colspan="2"><h4>Required Message</h4></th>
                                </tr>

                                <tr>
                                    <th scope="row">Title</th>
                                    <td>
                                        <input type="text" id="service_translations_zz_required_title" name="service_translations[zz][required][title]" value="(always required)" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Description</th>
                                    <td>
                                        <input type="text" id="service_translations_zz_required_description" name="service_translations[zz][required][description]" value="This services is always required" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" colspan="2"><h4>Purposes</h4></th>
                                </tr>

                                <tr>
                                    <th scope="row">Purpose (singular)</th>
                                    <td>
                                        <input type="text" id="service_translations_zz_purpose" name="service_translations[zz][purpose]" value="purpose" class="regular-text">
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Purposes (plural)</th>
                                    <td>
                                        <input type="text" id="service_translations_zz_purposes" name="service_translations[zz][purposes]" value="purposes" class="regular-text">
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Additional language tabs will be dynamically added here -->
                    </div>
                </div>

                <br> <button type="submit" class="button button-primary">Save Service</button>
                <button type="button" class="button button-primary add-cookie-group" data-index="0">Add Cookie Group</button> </form>
        </div>
    </div>
    <?php
}



add_action('wp_ajax_save_klaro_services', 'klaro_geo_save_services');

function klaro_geo_save_services(){
    klaro_geo_debug_log('$_POST: ' . print_r($_POST, true));

    if (isset($_POST['services'])) {
        // Initialize the service settings class
        $service_settings = new Klaro_Geo_Service_Settings();

        // Use wp_unslash() to remove escaping, only if necessary.
        $services = json_decode(wp_unslash($_POST['services']), true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($services)) {
            // Process each service to transform the name
            foreach ($services as $key => $service) {
                if (isset($service['name'])) {
                    // Convert to lowercase and replace spaces and underscores with hyphens
                    $services[$key]['name'] = str_replace(
                        array(' ', '_'),
                        '-',
                        strtolower($service['name'])
                    );
                }
            }

            // Save the services using the service settings class
            $service_settings->set($services);
            $service_settings->save();

            wp_send_json_success();
        } else {
            wp_send_json_error('Invalid services JSON or not an array');
        }
        klaro_geo_debug_log('Saved services: ' . print_r($services, true)); // Log saved services
    } else {
        wp_send_json_error('No services data provided');
   }
   wp_die();
}


add_action('wp_ajax_delete_klaro_service', 'klaro_geo_delete_service');

function klaro_geo_delete_service() {
    $index = isset($_POST['index']) ? intval($_POST['index']) : -1;
    if ($index < 0) {
        wp_send_json_error(['message' => 'Invalid service index.']);
        wp_die(); // Always wp_die() after wp_send_json_error()
    }

    // Initialize the service settings class
    $service_settings = new Klaro_Geo_Service_Settings();

    // Get the existing services
    $services = $service_settings->get();

    if (is_array($services) && isset($services[$index])) {
        array_splice($services, $index, 1); // Remove the service

        // Update the services using the service settings class
        $service_settings->set($services);
        $service_settings->save();

        wp_send_json_success($services);  // Send the updated services data
    } else {
        wp_send_json_error(['message' => 'Service not found or invalid services data.']);
    }
    wp_die();
}
?>
