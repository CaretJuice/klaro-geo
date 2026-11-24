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

# Docker creates these two directories, as root, to mount the plugin into
# them. WordPress has to be able to write beside the plugin (other plugins,
# uploads, debug.log). The plugin itself is mounted read-only and not touched.
chown www-data:www-data wp-content wp-content/plugins

if [ "${1:-}" = "reset" ]; then
    wp db reset --yes
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
