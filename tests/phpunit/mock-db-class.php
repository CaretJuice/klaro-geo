<?php
/**
 * Mock database implementation for tests using a class-based approach
 *
 * This file provides a mock implementation of the database functions
 * that can be used in tests without requiring actual database tables.
 */

// Configure error_log to write to debug.log
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/wp-content/debug.log');

// Helper function to log to debug.log
function mock_db_log($message) {
    // Write to debug.log
    error_log('[Mock DB] ' . $message);
}

// Define esc_sql if it doesn't exist
if (!function_exists('esc_sql')) {
    function esc_sql($string) {
        global $wpdb;
        if (isset($wpdb) && method_exists($wpdb, 'esc_like')) {
            return $wpdb->esc_like($string);
        }
        return addslashes($string);
    }
}

/**
 * Class to handle mock database operations
 */
class KlaroGeoMockDB {
    // Storage for mock data
    private static $storage = array();
    
    // Flag to indicate if mocking is enabled
    private static $enabled = false;
    
    // Original wpdb methods
    private static $original_wpdb_methods = array();
    
    /**
     * Enable mocking
     */
    public static function enable() {
        self::$enabled = true;
        self::setup_wpdb_mocks();
        mock_db_log('Mock database enabled');
    }
    
    /**
     * Disable mocking
     */
    public static function disable() {
        self::$enabled = false;
        self::restore_wpdb_methods();
        mock_db_log('Mock database disabled');
    }
    
    /**
     * Setup wpdb method mocks
     */
    private static function setup_wpdb_mocks() {
        global $wpdb;
        
        // Store original methods
        if (isset($wpdb->get_row)) {
            self::$original_wpdb_methods['get_row'] = $wpdb->get_row;
        }
        if (isset($wpdb->get_results)) {
            self::$original_wpdb_methods['get_results'] = $wpdb->get_results;
        }
        if (isset($wpdb->insert)) {
            self::$original_wpdb_methods['insert'] = $wpdb->insert;
        }
        if (isset($wpdb->query)) {
            self::$original_wpdb_methods['query'] = $wpdb->query;
        }
        if (isset($wpdb->get_var)) {
            self::$original_wpdb_methods['get_var'] = $wpdb->get_var;
        }
        if (isset($wpdb->prepare)) {
            self::$original_wpdb_methods['prepare'] = $wpdb->prepare;
        }
        
        // Set our mock methods
        $wpdb->get_row = array('KlaroGeoMockDB', 'get_row');
        $wpdb->get_results = array('KlaroGeoMockDB', 'get_results');
        $wpdb->insert = array('KlaroGeoMockDB', 'insert');
        $wpdb->query = array('KlaroGeoMockDB', 'query');
        $wpdb->get_var = array('KlaroGeoMockDB', 'get_var');
        $wpdb->prepare = array('KlaroGeoMockDB', 'prepare');
    }
    
    /**
     * Restore original wpdb methods
     */
    private static function restore_wpdb_methods() {
        global $wpdb;
        
        // Restore original methods
        foreach (self::$original_wpdb_methods as $method => $callback) {
            $wpdb->$method = $callback;
        }
        
        // Clear stored methods
        self::$original_wpdb_methods = array();
    }
    
    /**
     * Initialize the mock storage for a table
     */
    public static function init_table($table_name) {
        // Strip the prefix if it's included
        $table_name = str_replace('wp_', '', $table_name);
        $table_name = str_replace('wptests_', '', $table_name);

        if (!isset(self::$storage[$table_name])) {
            self::$storage[$table_name] = array();
            mock_db_log("Initialized mock table: $table_name");
        }

        // Also create a real table in the test database
        global $wpdb;
        $full_table_name = $wpdb->prefix . $table_name;

        // Check if the table already exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;

        if (!$table_exists) {
            mock_db_log("Creating real table in test database: $full_table_name");

            // Create the table with a simple structure
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $full_table_name (
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
                PRIMARY KEY (id)
            ) $charset_collate";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            // Check if the table was created
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;

            if (!$table_exists) {
                mock_db_log("Failed to create table: " . $wpdb->last_error);
                return false;
            }

            mock_db_log("Table created successfully: $full_table_name");
        }

        return true;
    }
    
    /**
     * Store a consent receipt
     */
    public static function store_consent_receipt($receipt_data) {
        mock_db_log("Using mock implementation of store_consent_receipt");

        // Check if consent logging is enabled for this template
        $template_name = isset($receipt_data['template_name']) ? $receipt_data['template_name'] : 'default';
        $templates = get_option('klaro_geo_templates', array());
        $template = isset($templates[$template_name]) ? $templates[$template_name] : null;

        // Default to true if setting doesn't exist
        $enable_consent_logging = true;

        if ($template && isset($template['wordpress_settings']['enable_consent_logging'])) {
            $enable_consent_logging = (bool) $template['wordpress_settings']['enable_consent_logging'];
        }

        // If consent logging is disabled for this template, return success without storing
        if (!$enable_consent_logging) {
            mock_db_log('Consent logging disabled for template: ' . $template_name . '. Receipt not stored.');
            return array(
                'success' => true,
                'data' => array(
                    'receipt_id' => null,
                    'message' => 'Consent logging disabled for this template'
                )
            );
        }

        // Initialize the table if it doesn't exist
        $table_name = 'klaro_geo_consent_receipts';
        self::init_table($table_name);

        // Generate an ID for the receipt
        $id = count(self::$storage[$table_name]) + 1;

        // Prepare the receipt data
        $receipt = array(
            'id' => $id,
            'receipt_id' => $receipt_data['receipt_id'],
            'user_id' => get_current_user_id() ?: null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'timestamp' => date('Y-m-d H:i:s', $receipt_data['timestamp']),
            'consent_data' => wp_json_encode($receipt_data['consent_choices']),
            'template_name' => $receipt_data['template_name'],
            'template_source' => $receipt_data['template_source'],
            'country_code' => $receipt_data['country_code'] ?? null,
            'region_code' => $receipt_data['region_code'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        // Store the receipt in our mock storage
        self::$storage[$table_name][$id] = $receipt;

        // Also insert into the real database
        global $wpdb;
        $full_table_name = $wpdb->prefix . $table_name;

        // Insert the data
        $result = $wpdb->insert(
            $full_table_name,
            array(
                'receipt_id' => $receipt_data['receipt_id'],
                'user_id' => get_current_user_id() ?: null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'timestamp' => date('Y-m-d H:i:s', $receipt_data['timestamp']),
                'consent_data' => wp_json_encode($receipt_data['consent_choices']),
                'template_name' => $receipt_data['template_name'],
                'template_source' => $receipt_data['template_source'],
                'country_code' => $receipt_data['country_code'] ?? null,
                'region_code' => $receipt_data['region_code'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            )
        );

        if ($result === false) {
            mock_db_log("Failed to insert receipt into database: " . $wpdb->last_error);
            return array(
                'success' => false,
                'data' => 'Failed to store receipt'
            );
        }

        // Get the real insert ID
        $real_id = $wpdb->insert_id;

        // Set the insert_id property
        $wpdb->insert_id = $real_id;

        mock_db_log("Receipt stored successfully with ID: $real_id");

        return array(
            'success' => true,
            'data' => array(
                'receipt_id' => $real_id
            )
        );
    }
    
    /**
     * Mock implementation of wpdb->get_row
     */
    public static function get_row($query, $output = OBJECT) {
        mock_db_log("Mock wpdb->get_row called with query: $query");

        // If we're enabled, use the real database
        if (self::$enabled) {
            // Call the original method if it exists
            if (isset(self::$original_wpdb_methods['get_row'])) {
                $original_method = self::$original_wpdb_methods['get_row'];
                if (is_callable($original_method)) {
                    return call_user_func($original_method, $query, $output);
                }
            }

            // Otherwise, use a direct query
            global $wpdb;
            $result = $wpdb->query($query);
            if ($result === false) {
                mock_db_log("Database error: " . $wpdb->last_error);
                return null;
            }

            // Get the result
            if ($output === OBJECT) {
                return $wpdb->get_row($query);
            } else {
                return $wpdb->get_row($query, $output);
            }
        }

        // Otherwise, use our mock storage
        // Check if this is a query for a consent receipt
        if (strpos($query, 'klaro_geo_consent_receipts') !== false) {
            $table_name = 'klaro_geo_consent_receipts';

            // Extract the receipt ID from the query
            if (preg_match('/receipt_id\s*=\s*[\'"]([^\'"]+)[\'"]/', $query, $matches)) {
                $receipt_id = $matches[1];

                // Find the receipt with this ID
                if (isset(self::$storage[$table_name])) {
                    foreach (self::$storage[$table_name] as $id => $receipt) {
                        if ($receipt['receipt_id'] === $receipt_id) {
                            // Convert the array to an object
                            return (object) $receipt;
                        }
                    }
                }
            }

            // Extract the ID from the query
            if (preg_match('/id\s*=\s*(\d+)/', $query, $matches)) {
                $id = (int) $matches[1];

                // Check if we have a receipt with this ID
                if (isset(self::$storage[$table_name]) && isset(self::$storage[$table_name][$id])) {
                    // Convert the array to an object
                    return (object) self::$storage[$table_name][$id];
                }
            }
        }

        return null;
    }
    
    /**
     * Mock implementation of wpdb->get_results
     */
    public static function get_results($query, $output = OBJECT) {
        mock_db_log("Mock wpdb->get_results called with query: $query");

        // If we're enabled, use the real database
        if (self::$enabled) {
            // Call the original method if it exists
            if (isset(self::$original_wpdb_methods['get_results'])) {
                $original_method = self::$original_wpdb_methods['get_results'];
                if (is_callable($original_method)) {
                    return call_user_func($original_method, $query, $output);
                }
            }

            // Otherwise, use a direct query
            global $wpdb;
            return $wpdb->get_results($query, $output);
        }

        // Otherwise, use our mock storage
        // Check if this is a query for consent receipts
        if (strpos($query, 'klaro_geo_consent_receipts') !== false) {
            $table_name = 'klaro_geo_consent_receipts';
            $results = array();
            
            // Extract LIMIT and OFFSET if present
            $limit = null;
            $offset = 0;
            if (preg_match('/LIMIT\s+(\d+)(?:\s+OFFSET\s+(\d+))?/i', $query, $matches)) {
                $limit = (int) $matches[1];
                if (isset($matches[2])) {
                    $offset = (int) $matches[2];
                }
            }
            
            // Extract ORDER BY if present
            $order_by_timestamp_desc = strpos($query, 'ORDER BY timestamp DESC') !== false;
            
            // Get all receipts
            $all_receipts = array();
            if (isset(self::$storage[$table_name])) {
                foreach (self::$storage[$table_name] as $id => $receipt) {
                    $all_receipts[] = $receipt;
                }
            }
            
            // Sort by timestamp if needed
            if ($order_by_timestamp_desc) {
                usort($all_receipts, function($a, $b) {
                    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
                });
            }
            
            // Apply pagination
            if ($limit !== null) {
                $all_receipts = array_slice($all_receipts, $offset, $limit);
            }
            
            // Convert to objects if needed
            foreach ($all_receipts as $receipt) {
                if ($output === OBJECT) {
                    $results[] = (object) $receipt;
                } else {
                    $results[] = $receipt;
                }
            }
            
            mock_db_log("Returning " . count($results) . " results (limit: " . ($limit ?? 'all') . ", offset: $offset)");
            return $results;
        }
        
        // For SHOW TABLES query, return a mock result
        if (strpos($query, 'SHOW TABLES') !== false) {
            $results = array();
            $results[] = array('wp_klaro_geo_consent_receipts');
            return $results;
        }
        
        return array();
    }
    
    /**
     * Mock implementation of wpdb->insert
     */
    public static function insert($table, $data, $format = null) {
        mock_db_log("Mock wpdb->insert called for table: $table");

        // If we're enabled, use the real database
        if (self::$enabled) {
            // Call the original method if it exists
            if (isset(self::$original_wpdb_methods['insert'])) {
                $original_method = self::$original_wpdb_methods['insert'];
                if (is_callable($original_method)) {
                    return call_user_func($original_method, $table, $data, $format);
                }
            }

            // Otherwise, use a direct query
            global $wpdb;
            return $wpdb->insert($table, $data, $format);
        }

        // Otherwise, use our mock storage
        // Check if this is for the consent receipts table
        if (strpos($table, 'klaro_geo_consent_receipts') !== false) {
            $table_name = 'wp_klaro_geo_consent_receipts';
            
            // Initialize the table if it doesn't exist
            self::init_table($table_name);
            
            // Generate an ID for the receipt
            $id = count(self::$storage[$table_name]) + 1;
            
            // Add the ID to the data
            $data['id'] = $id;
            
            // Store the data
            self::$storage[$table_name][$id] = $data;
            
            // Set the insert_id property
            global $wpdb;
            $wpdb->insert_id = $id;
            
            return 1; // Number of rows affected
        }
        
        return false;
    }
    
    /**
     * Mock implementation of wpdb->query
     */
    public static function query($query) {
        mock_db_log("Mock wpdb->query called with query: $query");

        // If we're enabled, use the real database
        if (self::$enabled) {
            // Call the original method if it exists
            if (isset(self::$original_wpdb_methods['query'])) {
                $original_method = self::$original_wpdb_methods['query'];
                if (is_callable($original_method)) {
                    return call_user_func($original_method, $query);
                }
            }

            // Otherwise, use a direct query
            global $wpdb;
            return $wpdb->query($query);
        }

        // Otherwise, use our mock storage
        // Check if this is a DROP TABLE query
        if (preg_match('/DROP TABLE IF EXISTS\s+([^\s;]+)/', $query, $matches)) {
            $table = $matches[1];
            
            // If this is for the consent receipts table, reset the storage
            if (strpos($table, 'klaro_geo_consent_receipts') !== false) {
                $table_name = 'wp_klaro_geo_consent_receipts';
                self::$storage[$table_name] = array();
            }
            
            return true;
        }
        
        // Check if this is a TRUNCATE TABLE query
        if (preg_match('/TRUNCATE TABLE\s+([^\s;]+)/', $query, $matches)) {
            $table = $matches[1];
            
            // If this is for the consent receipts table, reset the storage
            if (strpos($table, 'klaro_geo_consent_receipts') !== false) {
                $table_name = 'wp_klaro_geo_consent_receipts';
                self::$storage[$table_name] = array();
            }
            
            return true;
        }
        
        // Check if this is a CREATE TABLE query
        if (preg_match('/CREATE TABLE\s+([^\s(]+)/', $query, $matches)) {
            $table = $matches[1];
            
            // If this is for the consent receipts table, initialize the storage
            if (strpos($table, 'klaro_geo_consent_receipts') !== false) {
                $table_name = 'wp_klaro_geo_consent_receipts';
                self::init_table($table_name);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Mock implementation of wpdb->get_var
     */
    public static function get_var($query) {
        mock_db_log("Mock wpdb->get_var called with query: $query");

        // If we're enabled, use the real database
        if (self::$enabled) {
            // Call the original method if it exists
            if (isset(self::$original_wpdb_methods['get_var'])) {
                $original_method = self::$original_wpdb_methods['get_var'];
                if (is_callable($original_method)) {
                    return call_user_func($original_method, $query);
                }
            }

            // Otherwise, use a direct query
            global $wpdb;
            return $wpdb->get_var($query);
        }

        // Otherwise, use our mock storage
        // Check if this is a SHOW TABLES LIKE query
        if (preg_match('/SHOW TABLES LIKE\s+[\'"]([^\'"]+)[\'"]/', $query, $matches)) {
            $table = $matches[1];

            // If this is for the consent receipts table, return the table name
            if (strpos($table, 'klaro_geo_consent_receipts') !== false) {
                return $table;
            }
        }

        // Check if this is a COUNT query
        if (preg_match('/SELECT\s+COUNT\(\*\)\s+FROM\s+([^\s;]+)/i', $query, $matches)) {
            $table = $matches[1];

            // If this is for the consent receipts table, return the count
            if (strpos($table, 'klaro_geo_consent_receipts') !== false) {
                $table_name = 'klaro_geo_consent_receipts';
                if (isset(self::$storage[$table_name])) {
                    $count = count(self::$storage[$table_name]);
                    mock_db_log("Returning count: $count");
                    return $count;
                }
            }
        }

        // Check if this is a SELECT id query
        if (preg_match('/SELECT\s+id\s+FROM\s+([^\s;]+)\s+WHERE\s+receipt_id\s*=\s*[\'"]([^\'"]+)[\'"]/i', $query, $matches)) {
            $table = $matches[1];
            $receipt_id = $matches[2];

            // If this is for the consent receipts table, find the receipt and return its ID
            if (strpos($table, 'klaro_geo_consent_receipts') !== false) {
                $table_name = 'klaro_geo_consent_receipts';
                if (isset(self::$storage[$table_name])) {
                    foreach (self::$storage[$table_name] as $id => $receipt) {
                        if ($receipt['receipt_id'] === $receipt_id) {
                            mock_db_log("Found receipt with ID: $id");
                            return $id;
                        }
                    }
                }
            }
        }

        return null;
    }
    
    /**
     * Mock implementation of wpdb->prepare
     */
    public static function prepare($query, ...$args) {
        mock_db_log("Mock wpdb->prepare called with query: $query");
        
        // Simple implementation that replaces %s, %d, etc. with the provided arguments
        $i = 0;
        $prepared_query = preg_replace_callback('/%[sd]/', function($matches) use (&$i, $args) {
            if (isset($args[$i])) {
                $value = $args[$i++];
                // For strings, add quotes
                if ($matches[0] === '%s') {
                    return "'" . esc_sql($value) . "'";
                }
                // For numbers, just return the value
                return (int) $value;
            }
            return $matches[0];
        }, $query);
        
        return $prepared_query;
    }
}

// Note: The klaro_geo_test_store_consent_receipt function is defined in test-helpers.php