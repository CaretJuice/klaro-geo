#!/bin/bash
set -e

echo "=== Klaro Geo E2E Environment Setup ==="

# Wait for MySQL to be ready using PHP
echo "Waiting for MySQL to be ready..."
# Extract host without port
DB_HOST=$(echo "$WORDPRESS_DB_HOST" | cut -d: -f1)
until php -r "new mysqli('$DB_HOST', '$WORDPRESS_DB_USER', '$WORDPRESS_DB_PASSWORD', '$WORDPRESS_DB_NAME');" 2>/dev/null; do
    echo "Waiting for database connection..."
    sleep 2
done
echo "MySQL is ready!"

# Run the original WordPress entrypoint in the background to set up WordPress
echo "Starting WordPress setup..."
docker-entrypoint.sh apache2-foreground &
APACHE_PID=$!

# Wait for WordPress to be accessible
echo "Waiting for WordPress to be accessible..."
for i in {1..30}; do
    if curl -f http://localhost > /dev/null 2>&1; then
        echo "WordPress is accessible!"
        break
    fi
    echo "Attempt $i/30: Waiting for WordPress..."
    sleep 2
done

# Install WP-CLI
if [ ! -f /usr/local/bin/wp ]; then
    echo "Installing WP-CLI..."
    curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x wp-cli.phar
    mv wp-cli.phar /usr/local/bin/wp
    echo "WP-CLI installed successfully!"
fi

# Install WordPress if not already installed
if ! wp core is-installed --allow-root --path=/var/www/html 2>/dev/null; then
    echo "Installing WordPress..."
    wp core install \
        --url="${WP_SITE_URL:-http://localhost:8080}" \
        --title="${WP_SITE_TITLE:-Klaro Geo E2E Tests}" \
        --admin_user="${WP_ADMIN_USER:-admin}" \
        --admin_password="${WP_ADMIN_PASSWORD:-password}" \
        --admin_email="${WP_ADMIN_EMAIL:-admin@example.com}" \
        --skip-email \
        --allow-root \
        --path=/var/www/html
    echo "WordPress installed successfully!"
else
    echo "WordPress is already installed"
fi

# Install geoip-detect plugin if not present
if [ ! -d /var/www/html/wp-content/plugins/geoip-detect ]; then
    echo "Installing geoip-detect plugin..."
    wp plugin install geoip-detect --activate --allow-root --path=/var/www/html
    echo "geoip-detect plugin installed and activated!"
else
    echo "geoip-detect plugin already installed"
    wp plugin activate geoip-detect --allow-root --path=/var/www/html 2>/dev/null || true
fi

# Activate klaro-geo plugin
echo "Activating klaro-geo plugin..."
wp plugin activate klaro-geo --allow-root --path=/var/www/html 2>/dev/null || true
echo "klaro-geo plugin activated!"

# Set up Google Tag Manager ID for testing
echo "Setting up GTM configuration..."
wp option update klaro_geo_gtm_id 'GTM-M2Z9TF4J' --allow-root --path=/var/www/html 2>/dev/null || true
echo "GTM ID set to GTM-M2Z9TF4J"

# Enable consent receipt logging (must match klaro-geo.php:220)
wp option update klaro_geo_enable_consent_receipts 1 --allow-root --path=/var/www/html 2>/dev/null || true

# Let the plugin use its default services (which includes GTM as required:true, default:true)
# The default services are defined in includes/klaro-geo-defaults.php
echo "Using default plugin services (GTM, Google Analytics, Google Ads)"

# Set proper permissions
echo "Setting permissions..."
# Only set permissions for geoip-detect (not klaro-geo since it's a volume mount from host)
chown -R www-data:www-data /var/www/html/wp-content/plugins/geoip-detect 2>/dev/null || true
chmod -R 755 /var/www/html/wp-content/plugins/geoip-detect 2>/dev/null || true
echo "Permissions set successfully!"

echo "=== E2E Environment Ready! ==="
echo "WordPress Admin: http://localhost:8080/wp-admin"
echo "Username: ${WP_ADMIN_USER:-admin}"
echo "Password: ${WP_ADMIN_PASSWORD:-password}"
echo "================================"

# Wait for Apache to finish
wait $APACHE_PID
