#!/bin/bash
# This script helps debug database issues in the WordPress test container

# Change to the docker directory
cd "$(dirname "$0")"

# Make sure the container is running
if ! docker compose ps | grep -q wordpress_test; then
  echo "Starting WordPress test container..."
  docker compose up -d wordpress_test
  sleep 5
fi

# Create a simple PHP script to test database connectivity
cat > test-db.php <<EOF
<?php
// Test script for WordPress database connectivity

// Output PHP and MySQL information
echo "PHP Version: " . PHP_VERSION . "\n";
echo "MySQL Client Info: " . mysqli_get_client_info() . "\n\n";

// Try to connect to the database directly
\$host = 'db_test';
\$user = 'wordpress';
\$pass = 'wordpress';
\$db = 'wordpress_test';

echo "Attempting direct MySQL connection to \$host as \$user...\n";
\$mysqli = new mysqli(\$host, \$user, \$pass, \$db);

if (\$mysqli->connect_error) {
    echo "Direct connection failed: " . \$mysqli->connect_error . "\n";
} else {
    echo "Direct connection successful!\n";
    
    // Check if we can create tables
    echo "\nTesting table creation...\n";
    \$table_name = 'test_table_' . time();
    \$result = \$mysqli->query("CREATE TABLE \$table_name (id INT AUTO_INCREMENT PRIMARY KEY, test_value VARCHAR(255))");
    
    if (\$result) {
        echo "Table \$table_name created successfully\n";
        
        // Insert some data
        \$mysqli->query("INSERT INTO \$table_name (test_value) VALUES ('test1'), ('test2')");
        
        // Retrieve the data
        \$result = \$mysqli->query("SELECT * FROM \$table_name");
        echo "Retrieved " . \$result->num_rows . " rows from \$table_name\n";
        
        // Drop the table
        \$mysqli->query("DROP TABLE \$table_name");
        echo "Table \$table_name dropped\n";
    } else {
        echo "Failed to create table: " . \$mysqli->error . "\n";
    }
    
    // List all tables
    echo "\nListing all tables in database:\n";
    \$result = \$mysqli->query("SHOW TABLES");
    while (\$row = \$result->fetch_array()) {
        echo "- " . \$row[0] . "\n";
    }
    
    \$mysqli->close();
}

// Now try using WordPress's wpdb
echo "\nAttempting to use WordPress's wpdb...\n";
require_once '/var/www/html/wp-load.php';
global \$wpdb;

echo "WordPress database prefix: " . \$wpdb->prefix . "\n";
echo "WordPress database server version: " . \$wpdb->db_version() . "\n";

// Test creating a table with wpdb
\$test_table = \$wpdb->prefix . 'wpdb_test_' . time();
echo "\nTesting table creation with wpdb...\n";
\$result = \$wpdb->query("CREATE TABLE \$test_table (id INT AUTO_INCREMENT PRIMARY KEY, test_value VARCHAR(255))");

if (\$result !== false) {
    echo "Table \$test_table created successfully\n";
    
    // Insert some data
    \$wpdb->insert(\$test_table, array('test_value' => 'wpdb test'));
    echo "Inserted row with ID: " . \$wpdb->insert_id . "\n";
    
    // Drop the table
    \$wpdb->query("DROP TABLE \$test_table");
    echo "Table \$test_table dropped\n";
} else {
    echo "Failed to create table with wpdb: " . \$wpdb->last_error . "\n";
}

// Check database permissions
echo "\nChecking database permissions...\n";
\$grants = \$wpdb->get_results("SHOW GRANTS FOR CURRENT_USER()");
foreach (\$grants as \$grant) {
    echo reset(\$grant) . "\n";
}

// List all tables
echo "\nListing all tables with wpdb:\n";
\$tables = \$wpdb->get_results("SHOW TABLES", ARRAY_N);
foreach (\$tables as \$table) {
    echo "- " . \$table[0] . "\n";
}
EOF

# Copy the test script to the container
docker compose exec wordpress_test mkdir -p /tmp/test
docker compose cp test-db.php wordpress_test:/tmp/test/

# Run the test script
echo "Running database test script..."
docker compose exec wordpress_test php /tmp/test/test-db.php

# Clean up
rm test-db.php
echo -e "\nTest completed."