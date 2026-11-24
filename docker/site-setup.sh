#!/bin/bash
# Bring the site to its known state: WordPress installed, Klaro Geo and
# Geolocation IP Detection active, a GTM ID set and consent receipts on.
#
#   site-setup          install if WordPress is not installed yet
#   site-setup reset    empty the database first, so the state is exact
#
# Run inside the site container by bin/site.sh. The Playwright tests rely on
# the state this leaves, so change the two together.
set -euo pipefail

cd /var/www/html

if [ "${1:-}" = "reset" ]; then
    php -r '
        mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
        $db   = getenv( "WORDPRESS_DB_NAME" );
        $conn = new mysqli( getenv( "WORDPRESS_DB_HOST" ), getenv( "WORDPRESS_DB_USER" ), getenv( "WORDPRESS_DB_PASSWORD" ) );
        $conn->query( "DROP DATABASE IF EXISTS `$db`" );
        $conn->query( "CREATE DATABASE `$db`" );
    '
fi

if ! wp core is-installed 2>/dev/null; then
    wp core install \
        --url="http://localhost:8080" \
        --title="Klaro Geo" \
        --admin_user=admin \
        --admin_password=password \
        --admin_email=admin@example.com \
        --skip-email
fi

wp plugin activate geoip-detect klaro-geo
wp option update klaro_geo_gtm_id 'GTM-M2Z9TF4J'
wp option update klaro_geo_enable_consent_receipts 1

echo "Site ready: http://localhost:8080 (admin / password)"
