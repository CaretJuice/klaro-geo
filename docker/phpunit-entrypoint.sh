#!/bin/bash
# Entrypoint of the phpunit service: give the run an empty test database, then
# run the command. The database server is already up (depends_on in
# docker-compose.yml).
set -euo pipefail

php -r '
    mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
    $db   = getenv( "WP_TESTS_DB_NAME" );
    $conn = new mysqli( getenv( "WP_TESTS_DB_HOST" ), getenv( "WP_TESTS_DB_USER" ), getenv( "WP_TESTS_DB_PASSWORD" ) );
    $conn->query( "DROP DATABASE IF EXISTS `$db`" );
    $conn->query( "CREATE DATABASE `$db`" );
'

exec "$@"
