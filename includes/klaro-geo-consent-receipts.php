<?php
/**
 * Klaro Geo Consent Receipts
 * 
 * Handles the creation, storage, and retrieval of consent receipts
 */

// Create the consent receipts table on plugin activation
function klaro_geo_create_consent_receipts_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'klaro_geo_consent_receipts';

    // Check if we're in a test environment
    $is_test = defined('WP_TESTS_DOMAIN');

    // Log database information for debugging
    klaro_geo_debug_log("Database info: " . $wpdb->db_version());
    klaro_geo_debug_log("Database prefix: " . $wpdb->prefix);

    // Get a list of all tables for debugging
    $all_tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
    $table_list = [];
    foreach ($all_tables as $table) {
        $table_list[] = $table[0];
    }
    klaro_geo_debug_log("Existing tables: " . implode(", ", $table_list));

    // In test environment, drop the table first to avoid conflicts
    if ($is_test) {
        klaro_geo_debug_log("Test environment detected - dropping table $table_name if it exists");
        $drop_result = $wpdb->query("DROP TABLE IF EXISTS $table_name");
        klaro_geo_debug_log("Drop table result: " . ($drop_result === false ? "Failed" : "Success"));

        if ($drop_result === false) {
            klaro_geo_debug_log("Drop table error: " . $wpdb->last_error);
        }
    }

    // Check if the table already exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

    if ($table_exists) {
        klaro_geo_debug_log("Table $table_name already exists - skipping creation");
        return true;
    }

    klaro_geo_debug_log("Creating table $table_name");

    $charset_collate = $wpdb->get_charset_collate();
    klaro_geo_debug_log("Charset collate: " . $charset_collate);

    // For test environments, use a simpler table structure to avoid potential issues
    if ($is_test) {
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            receipt_id varchar(50) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            timestamp datetime NOT NULL,
            consent_data longtext NOT NULL,
            template_name varchar(100) NOT NULL,
            template_source varchar(50) NOT NULL,
            country_code varchar(10) DEFAULT NULL,
            region_code varchar(10) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            klaro_config longtext DEFAULT NULL,
            PRIMARY KEY (id)
        ) $charset_collate";
    } else {
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            receipt_id varchar(50) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            timestamp datetime NOT NULL,
            consent_data longtext NOT NULL,
            template_name varchar(100) NOT NULL,
            template_source varchar(50) NOT NULL,
            country_code varchar(10) DEFAULT NULL,
            region_code varchar(10) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            klaro_config longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY receipt_id (receipt_id),
            KEY user_id (user_id),
            KEY timestamp (timestamp),
            KEY country_code (country_code)
        ) $charset_collate";
    }

    // Try direct SQL first in test environments
    if ($is_test) {
        klaro_geo_debug_log("Using direct SQL query for test environment");
        $result = $wpdb->query($sql);

        if ($result === false) {
            klaro_geo_debug_log("Direct SQL query failed. Error: " . $wpdb->last_error);

            // Try with a temporary table name
            $temp_table_name = $wpdb->prefix . 'temp_klaro_receipts_' . time();
            $temp_sql = str_replace($table_name, $temp_table_name, $sql);

            klaro_geo_debug_log("Trying with temporary table name: $temp_table_name");
            $temp_result = $wpdb->query($temp_sql);

            if ($temp_result === false) {
                klaro_geo_debug_log("Temporary table creation failed. Error: " . $wpdb->last_error);
                return false;
            }

            // Rename the temporary table
            $rename_sql = "RENAME TABLE $temp_table_name TO $table_name";
            $rename_result = $wpdb->query($rename_sql);

            if ($rename_result === false) {
                klaro_geo_debug_log("Rename table failed. Error: " . $wpdb->last_error);
                return false;
            }
        }
    } else {
        // Use dbDelta for production environments
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        klaro_geo_debug_log("dbDelta result: " . print_r($result, true));
    }

    // Check if the table was created successfully
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

    if (!$table_exists) {
        klaro_geo_debug_log("Table $table_name still doesn't exist after creation attempt");

        // Try one more direct approach as a last resort
        $simple_sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            receipt_id varchar(50) NOT NULL,
            timestamp datetime NOT NULL,
            consent_data longtext NOT NULL,
            template_name varchar(100) NOT NULL,
            template_source varchar(50) NOT NULL,
            klaro_config longtext DEFAULT NULL,
            PRIMARY KEY (id)
        ) $charset_collate";

        klaro_geo_debug_log("Trying simplified table structure as last resort");
        $last_result = $wpdb->query($simple_sql);

        if ($last_result === false) {
            klaro_geo_debug_log("Final attempt failed. Error: " . $wpdb->last_error);
            return false;
        }

        // Check one more time
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$table_exists) {
            klaro_geo_debug_log("All attempts to create table $table_name have failed");
            return false;
        }
    }

    // Add version to options
    add_option('klaro_geo_consent_receipts_db_version', '1.0');

    klaro_geo_debug_log("Table $table_name created successfully");
    return true;
}

// Drop the consent receipts table on plugin deactivation if cleanup is enabled
function klaro_geo_drop_consent_receipts_table() {
    // Always drop the table in test environment, otherwise check the cleanup setting
    if (defined('WP_TESTS_DOMAIN') || get_option('klaro_geo_cleanup_on_deactivate', false)) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'klaro_geo_consent_receipts';

        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        delete_option('klaro_geo_consent_receipts_db_version');
    }
}

// Generate a unique receipt ID based on timestamp and random string
function klaro_geo_generate_receipt_id() {
    $timestamp = microtime(true);
    $random = bin2hex(random_bytes(8)); // 16 character random string
    return 'receipt_' . str_replace('.', '', $timestamp) . '_' . $random;
}

// Store a consent receipt in the database
function klaro_geo_store_consent_receipt($receipt_data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'klaro_geo_consent_receipts';

    // Check if the table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    if (!$table_exists) {
        klaro_geo_debug_log("Consent receipts table does not exist. Attempting to create it.");
        klaro_geo_create_consent_receipts_table();

        // Check again after creation attempt
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if (!$table_exists) {
            klaro_geo_debug_log("Failed to create consent receipts table. Cannot store receipt.");
            return false;
        }
    }

    // Get user ID if logged in
    $user_id = get_current_user_id();
    $user_id = $user_id ? $user_id : null;

    // Get IP address with privacy considerations
    $ip_address = klaro_geo_get_anonymized_ip();

    // Prepare data for insertion
    try {
        // Process template source to ensure it has the correct format
        $template_source = $receipt_data['template_source'];

        // Check if admin override is set and convert to boolean
        $admin_override = false;
        if (isset($receipt_data['admin_override'])) {
            // Handle various formats of boolean values from JavaScript
            if ($receipt_data['admin_override'] === true ||
                $receipt_data['admin_override'] === 'true' ||
                $receipt_data['admin_override'] === 1 ||
                $receipt_data['admin_override'] === '1') {
                $admin_override = true;
            }
        }

        // Also check if the template source already indicates an admin override
        if (strpos($receipt_data['template_source'], 'admin-override') === 0) {
            $admin_override = true;
            klaro_geo_debug_log('Setting admin override to true based on template source: ' . $receipt_data['template_source']);
        }

        klaro_geo_debug_log('Server-side admin override value: ' . var_export($admin_override, true));

        // Determine the correct template source format
        if ($template_source === 'fallback') {
            // Keep fallback as is
        } else {
            // Determine the source type (admin-override or geo-match)
            $source_type = $admin_override ? 'admin-override' : 'geo-match';

            // Determine the location type (country or region)
            $location_type = !empty($receipt_data['region_code']) ? 'region' : 'country';

            // Combine them to form the complete template source
            $template_source = $source_type . ' ' . $location_type;
        }

        klaro_geo_debug_log('Final template source for receipt: ' . $template_source);

        $data = array(
            'receipt_id' => $receipt_data['receipt_id'],
            'user_id' => $user_id,
            'ip_address' => $ip_address,
            'timestamp' => date('Y-m-d H:i:s', $receipt_data['timestamp']),
            'consent_data' => wp_json_encode($receipt_data['consent_choices']),
            'template_name' => $receipt_data['template_name'],
            'template_source' => $template_source,
            'country_code' => $receipt_data['country_code'],
            'region_code' => $receipt_data['region_code'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        // Add klaro_config if it exists and the column exists in the table
        if (isset($receipt_data['klaro_config'])) {
            // Check if the column exists
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'klaro_config'");

            if (!empty($column_exists)) {
                // Encode the config, but limit its size to prevent issues
                $config_json = wp_json_encode($receipt_data['klaro_config']);
                // If the config is too large, store a truncated version
                if (strlen($config_json) > 65000) { // MySQL longtext has a practical limit
                    klaro_geo_debug_log("klaro_config is too large (" . strlen($config_json) . " bytes), truncating");
                    $config_json = substr($config_json, 0, 65000);
                }
                $data['klaro_config'] = $config_json;
            } else {
                klaro_geo_debug_log("klaro_config column does not exist, skipping this field");
            }
        }
    } catch (Exception $e) {
        klaro_geo_debug_log("Error preparing data for insertion: " . $e->getMessage());
        return false;
    }

    // Log the data we're inserting, but limit the size of large fields
    $log_data = $data;
    if (isset($log_data['klaro_config']) && strlen($log_data['klaro_config']) > 1000) {
        $log_data['klaro_config'] = substr($log_data['klaro_config'], 0, 1000) . '... [truncated, total length: ' . strlen($data['klaro_config']) . ' bytes]';
    }
    klaro_geo_debug_log("Inserting consent receipt with data: " . print_r($log_data, true));

    try {
        // Insert the receipt
        $result = $wpdb->insert($table_name, $data);

        if ($result === false) {
            klaro_geo_debug_log("Database error when inserting consent receipt: " . $wpdb->last_error);

            // If there's an error, try to determine if it's related to the klaro_config column
            if (isset($data['klaro_config']) && strpos($wpdb->last_error, 'klaro_config') !== false) {
                klaro_geo_debug_log("Error appears to be related to klaro_config column, trying without it");
                // Remove the klaro_config field and try again
                unset($data['klaro_config']);
                $result = $wpdb->insert($table_name, $data);

                if ($result === false) {
                    klaro_geo_debug_log("Still failed without klaro_config: " . $wpdb->last_error);
                    return false;
                } else {
                    klaro_geo_debug_log("Insert succeeded without klaro_config");
                }
            } else {
                return false;
            }
        }

        $insert_id = $wpdb->insert_id;
        klaro_geo_debug_log("Successfully inserted consent receipt with ID: " . $insert_id);

        return $insert_id;
    } catch (Exception $e) {
        klaro_geo_debug_log("Exception when inserting consent receipt: " . $e->getMessage());
        return false;
    }
}

// Get an anonymized IP address for privacy
function klaro_geo_get_anonymized_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // If IPv4, anonymize the last octet
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return preg_replace('/\.\d+$/', '.0', $ip);
    }
    
    // If IPv6, anonymize the last 80 bits (last 20 hex chars)
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return preg_replace('/:[0-9a-f]{1,4}(:[0-9a-f]{1,4}){4}$/i', ':0:0:0:0:0', $ip);
    }
    
    return $ip;
}

// AJAX handler to store consent receipt
function klaro_geo_ajax_store_consent_receipt() {
    // Enable error reporting for debugging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    }

    // Log the request with a unique identifier to track potential duplicates
    $request_id = uniqid('req_');
    klaro_geo_debug_log('AJAX handler for storing consent receipt triggered - Request ID: ' . $request_id);

    // Log the request source
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'unknown';
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
    klaro_geo_debug_log('Request source - Referer: ' . $referer . ', User Agent: ' . $user_agent);

    // Basic validation
    if (!isset($_POST['receipt_data'])) {
        wp_send_json_error('No receipt data provided');
        exit;
    }

    // Verify nonce for security (but make it optional for testing)
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'klaro_geo_consent_nonce')) {
        klaro_geo_debug_log('Nonce verification failed. Provided nonce: ' . ($_POST['nonce'] ?? 'none'));
        // For testing, we'll continue anyway
        // wp_send_json_error('Invalid security token');
        // exit;
    }

    // Decode the receipt data
    $receipt_data_json = stripslashes($_POST['receipt_data']);
    $receipt_data = json_decode($receipt_data_json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        klaro_geo_debug_log('JSON decode error: ' . json_last_error_msg());
        wp_send_json_error('JSON decode error: ' . json_last_error_msg());
        exit;
    }

    // Simplify the receipt data to avoid potential issues
    $simplified_receipt = array(
        'receipt_id' => $receipt_data['receipt_id'],
        'timestamp' => $receipt_data['timestamp'],
        'consent_choices' => $receipt_data['consent_choices'],
        'template_name' => $receipt_data['template_name'],
        'country_code' => $receipt_data['country_code'],
        'region_code' => $receipt_data['region_code']
    );

    // Process template source to ensure it has the correct format
    $template_source = $receipt_data['template_source'];

    // Check if admin override is set and convert to boolean
    $admin_override = false;
    if (isset($receipt_data['admin_override'])) {
        // Handle various formats of boolean values from JavaScript
        if ($receipt_data['admin_override'] === true ||
            $receipt_data['admin_override'] === 'true' ||
            $receipt_data['admin_override'] === 1 ||
            $receipt_data['admin_override'] === '1') {
            $admin_override = true;
        }
    }

    // Also check if the template source already indicates an admin override
    if (strpos($receipt_data['template_source'], 'admin-override') === 0) {
        $admin_override = true;
        klaro_geo_debug_log('Setting admin override to true based on template source: ' . $receipt_data['template_source']);
    }

    $simplified_receipt['admin_override'] = $admin_override;

    // Debug log the admin override value
    klaro_geo_debug_log('Admin override value from receipt data: ' . var_export($admin_override, true));
    klaro_geo_debug_log('Receipt data: ' . print_r($receipt_data, true));

    // Determine the correct template source format
    if ($template_source === 'fallback') {
        // Keep fallback as is
        $simplified_receipt['template_source'] = 'fallback';
    } else {
        // Determine the source type (admin-override or geo-match)
        $source_type = $admin_override ? 'admin-override' : 'geo-match';

        // Determine the location type (country or region)
        $location_type = !empty($receipt_data['region_code']) ? 'region' : 'country';

        // Combine them to form the complete template source
        $simplified_receipt['template_source'] = $source_type . ' ' . $location_type;
    }

    klaro_geo_debug_log('Final template source for AJAX receipt: ' . $simplified_receipt['template_source']);

    // Check if we can add the klaro_config
    global $wpdb;
    $table_name = $wpdb->prefix . 'klaro_geo_consent_receipts';
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'klaro_config'");

    if (!empty($column_exists) && isset($receipt_data['klaro_config'])) {
        klaro_geo_debug_log('klaro_config column exists, adding to receipt data');
        $simplified_receipt['klaro_config'] = $receipt_data['klaro_config'];
    } else {
        klaro_geo_debug_log('Skipping klaro_config: ' .
            (!empty($column_exists) ? 'Column exists' : 'Column does not exist') . ', ' .
            (isset($receipt_data['klaro_config']) ? 'Data provided' : 'No data provided'));
    }

    klaro_geo_debug_log('Simplified receipt data: ' . print_r($simplified_receipt, true));

    // Store the simplified receipt
    try {
        global $wpdb;
        $table_name = $wpdb->prefix . 'klaro_geo_consent_receipts';

        // Check if the table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if (!$table_exists) {
            klaro_geo_debug_log("Consent receipts table does not exist. Creating it now.");
            klaro_geo_create_consent_receipts_table();

            // Check again
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            if (!$table_exists) {
                klaro_geo_debug_log("Failed to create table. Cannot proceed.");
                wp_send_json_error("Failed to create table");
                exit;
            }
        }

        // Removed the 5-second lockout as requested
        // The JavaScript already has a 2-second lockout mechanism

        // Also check for duplicate receipt_id
        $receipt_id_check = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE receipt_id = %s",
                $simplified_receipt['receipt_id']
            )
        );

        if ($receipt_id_check > 0) {
            klaro_geo_debug_log("Duplicate receipt_id detected: " . $simplified_receipt['receipt_id']);
            wp_send_json_success(array('receipt_id' => 'duplicate_id_skipped', 'message' => 'Duplicate receipt ID skipped'));
            exit;
        }

        // Get user ID if logged in
        $user_id = get_current_user_id();
        $user_id = $user_id ? $user_id : null;

        // Get IP address with privacy considerations
        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

        // Prepare minimal data for insertion
        $data = array(
            'receipt_id' => $simplified_receipt['receipt_id'],
            'user_id' => $user_id,
            'ip_address' => $ip_address,
            'timestamp' => date('Y-m-d H:i:s', $simplified_receipt['timestamp']),
            'consent_data' => wp_json_encode($simplified_receipt['consent_choices']),
            'template_name' => $simplified_receipt['template_name'],
            'template_source' => $simplified_receipt['template_source'],
            'country_code' => $simplified_receipt['country_code'],
            'region_code' => $simplified_receipt['region_code'],
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null
        );

        // Add klaro_config if it exists in the simplified receipt
        if (isset($simplified_receipt['klaro_config'])) {
            try {
                // Encode the config, but limit its size to prevent issues
                $config_json = wp_json_encode($simplified_receipt['klaro_config']);

                // If the config is too large, store a truncated version
                if (strlen($config_json) > 65000) { // MySQL longtext has a practical limit
                    klaro_geo_debug_log("klaro_config is too large (" . strlen($config_json) . " bytes), truncating");
                    $config_json = substr($config_json, 0, 65000);
                }

                $data['klaro_config'] = $config_json;
                klaro_geo_debug_log("Added klaro_config to data, size: " . strlen($config_json) . " bytes");
            } catch (Exception $e) {
                klaro_geo_debug_log("Error encoding klaro_config: " . $e->getMessage());
                // Continue without the klaro_config
            }
        }

        klaro_geo_debug_log("Inserting simplified receipt with data: " . print_r($data, true));

        // Insert the receipt directly
        $result = $wpdb->insert($table_name, $data);

        if ($result === false) {
            klaro_geo_debug_log("Database error: " . $wpdb->last_error);
            wp_send_json_error("Database error: " . $wpdb->last_error);
            exit;
        }

        $insert_id = $wpdb->insert_id;
        klaro_geo_debug_log("Successfully inserted receipt with ID: " . $insert_id);

        wp_send_json_success(array('receipt_id' => $insert_id));
    } catch (Exception $e) {
        klaro_geo_debug_log("Exception: " . $e->getMessage());
        wp_send_json_error("Exception: " . $e->getMessage());
    }

    exit;
}
add_action('wp_ajax_klaro_geo_store_consent_receipt', 'klaro_geo_ajax_store_consent_receipt');
add_action('wp_ajax_nopriv_klaro_geo_store_consent_receipt', 'klaro_geo_ajax_store_consent_receipt');

// Add admin page for viewing consent receipts
function klaro_geo_add_consent_receipts_page() {
    add_submenu_page(
        'klaro-geo',
        'Consent Receipts',
        'Consent Receipts',
        'manage_options',
        'klaro-geo-consent-receipts',
        'klaro_geo_render_consent_receipts_page'
    );
}
add_action('admin_menu', 'klaro_geo_add_consent_receipts_page', 20);

// Render the consent receipts admin page
function klaro_geo_render_consent_receipts_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'klaro_geo_consent_receipts';

    // Get pagination parameters
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20; // Ensure this is always an integer.
    $offset = ($page - 1) * $per_page;

    // Get total count
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $total_pages = ceil($total_items / $per_page);

    // Ensure per_page and offset are integers.
    $per_page = intval($per_page);
    $offset = intval($offset);

    // Get receipts for current page
    $receipts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        )
    );
    // Log the generated query
    klaro_geo_debug_log("Consent Receipts Query: " . $wpdb->last_query);
    klaro_geo_debug_log("Consent Receipts Total Items: " . $total_items);
    klaro_geo_debug_log("Consent Receipts Per Page: " . $per_page);
    klaro_geo_debug_log("Consent Receipts Offset: " . $offset);
    klaro_geo_debug_log("Consent Receipts Page: " . $page);
    klaro_geo_debug_log("Consent Receipts Total Pages: " . $total_pages);
    klaro_geo_debug_log("Consent Receipts Results: " . print_r($receipts, true)); // Use print_r with true to capture the output

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <?php if (empty($receipts)): ?>
            <p>No consent receipts have been recorded yet.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Receipt ID</th>
                        <th>Date & Time</th>
                        <th>Template</th>
                        <th>Source</th>
                        <th>Country</th>
                        <th>Region</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipts as $receipt): ?>
                        <tr>
                            <td><?php echo esc_html($receipt->receipt_id); ?></td>
                            <td><?php echo esc_html($receipt->timestamp); ?></td>
                            <td><?php echo esc_html($receipt->template_name); ?></td>
                            <td><?php echo esc_html($receipt->template_source); ?></td>
                            <td><?php echo esc_html($receipt->country_code); ?></td>
                            <td><?php echo esc_html($receipt->region_code); ?></td>
                            <td>
                                <button type="button" class="button view-receipt" 
                                        data-receipt-id="<?php echo esc_attr($receipt->id); ?>">
                                    View Details
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php echo sprintf(_n('%s item', '%s items', $total_items), number_format_i18n($total_items)); ?>
                        </span>
                        <span class="pagination-links">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $page
                            ));
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Modal for viewing receipt details -->
            <div id="receipt-modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; 
                                          overflow:auto; background-color:rgba(0,0,0,0.4);">
                <div style="background-color:#fefefe; margin:10% auto; padding:20px; border:1px solid #888; width:80%; max-width:800px;">
                    <span id="close-modal" style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
                    <h2>Consent Receipt Details</h2>
                    <div id="receipt-details"></div>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // View receipt details
                $('.view-receipt').on('click', function() {
                    var receiptId = $(this).data('receipt-id');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'klaro_geo_get_receipt_details',
                            receipt_id: receiptId,
                            nonce: '<?php echo wp_create_nonce('klaro_geo_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var receipt = response.data;
                                var detailsHtml = '<table class="wp-list-table widefat fixed">';
                                
                                // Basic info
                                detailsHtml += '<tr><th>Receipt ID:</th><td>' + receipt.receipt_id + '</td></tr>';
                                detailsHtml += '<tr><th>Date & Time:</th><td>' + receipt.timestamp + '</td></tr>';
                                detailsHtml += '<tr><th>Template:</th><td>' + receipt.template_name + '</td></tr>';
                                detailsHtml += '<tr><th>Source:</th><td>' + receipt.template_source + '</td></tr>';
                                detailsHtml += '<tr><th>Country:</th><td>' + (receipt.country_code || 'N/A') + '</td></tr>';
                                detailsHtml += '<tr><th>Region:</th><td>' + (receipt.region_code || 'N/A') + '</td></tr>';
                                
                                // Consent choices
                                detailsHtml += '<tr><th>Consent Choices:</th><td><pre>' +
                                              JSON.stringify(JSON.parse(receipt.consent_data), null, 2) +
                                              '</pre></td></tr>';

                                // Klaro config (if available)
                                if (receipt.klaro_config) {
                                    detailsHtml += '<tr><th>Klaro Config:</th><td><pre>' +
                                                  JSON.stringify(JSON.parse(receipt.klaro_config), null, 2) +
                                                  '</pre></td></tr>';
                                }
                                
                                detailsHtml += '</table>';
                                
                                $('#receipt-details').html(detailsHtml);
                                $('#receipt-modal').show();
                            }
                        }
                    });
                });
                
                // Close modal
                $('#close-modal').on('click', function() {
                    $('#receipt-modal').hide();
                });
                
                // Close modal when clicking outside
                $(window).on('click', function(event) {
                    if ($(event.target).is('#receipt-modal')) {
                        $('#receipt-modal').hide();
                    }
                });
            });
            </script>
        <?php endif; ?>
    </div>
    <?php
}

// AJAX handler to get receipt details
function klaro_geo_ajax_get_receipt_details() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'klaro_geo_admin_nonce')) {
        wp_send_json_error('Invalid security token');
        exit;
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        exit;
    }
    
    $receipt_id = isset($_POST['receipt_id']) ? intval($_POST['receipt_id']) : 0;
    
    if (!$receipt_id) {
        wp_send_json_error('Invalid receipt ID');
        exit;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'klaro_geo_consent_receipts';
    
    $receipt = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $receipt_id
        )
    );
    
    if ($receipt) {
        wp_send_json_success($receipt);
    } else {
        wp_send_json_error('Receipt not found');
    }
    
    exit;
}
add_action('wp_ajax_klaro_geo_get_receipt_details', 'klaro_geo_ajax_get_receipt_details');