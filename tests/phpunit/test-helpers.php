<?php
/**
 * Test helper functions
 */

/**
 * Test-specific version of the AJAX handler that doesn't call exit()
 * This function replicates the logic in klaro_geo_ajax_get_receipt_details()
 * but returns the response instead of sending it with wp_send_json_*
 */
function klaro_geo_test_get_receipt_details($receipt_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'klaro_geo_consent_receipts';

    $receipt = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $receipt_id
        )
    );

    if ($receipt) {
        return ['success' => true, 'data' => $receipt];
    } else {
        return ['success' => false, 'data' => 'Receipt not found'];
    }
}

/**
 * Mock version of wp_send_json_success that doesn't exit
 * This is used to capture the response in tests
 */
function wp_send_json_success_no_exit($data, $status_code = null) {
    echo json_encode(['success' => true, 'data' => $data]);
}

/**
 * Mock version of wp_send_json_error that doesn't exit
 * This is used to capture the response in tests
 */
function wp_send_json_error_no_exit($data, $status_code = null) {
    echo json_encode(['success' => false, 'data' => $data]);
}

/**
 * Test-specific version of the AJAX handler for storing consent receipts
 * This function replicates the logic in klaro_geo_ajax_store_consent_receipt()
 * but returns the response instead of sending it with wp_send_json_*
 */
function klaro_geo_test_store_consent_receipt() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'klaro_geo_consent_nonce')) {
        return ['success' => false, 'data' => 'Invalid security token'];
    }

    // Get the receipt data from POST
    $receipt_data = isset($_POST['receipt_data']) ? json_decode(stripslashes($_POST['receipt_data']), true) : null;

    if (!$receipt_data) {
        return ['success' => false, 'data' => 'Invalid receipt data'];
    }

    // Use our mock class to store the receipt
    return KlaroGeoMockDB::store_consent_receipt($receipt_data);
}
