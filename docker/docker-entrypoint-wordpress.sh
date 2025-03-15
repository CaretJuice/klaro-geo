#!/bin/bash
set -e

# Create logs directory with proper permissions
mkdir -p /var/www/html/wp-content/plugins/klaro-geo/docker/logs
touch /var/www/html/wp-content/plugins/klaro-geo/docker/logs/debug_dev.log
chmod 666 /var/www/html/wp-content/plugins/klaro-geo/docker/logs/debug_dev.log

# Install geoip-detect plugin if it doesn't exist
if [ ! -d /var/www/html/wp-content/plugins/geoip-detect ]; then
  echo "Installing geoip-detect plugin..."
  mkdir -p /var/www/html/wp-content/plugins
  cd /var/www/html/wp-content/plugins
  curl -L https://downloads.wordpress.org/plugin/geoip-detect.latest-stable.zip -o geoip-detect.zip
  unzip geoip-detect.zip
  rm geoip-detect.zip
  echo "geoip-detect plugin installed successfully"
fi

# Set proper permissions
echo "Setting permissions..."
chown -R www-data:www-data /var/www/html/wp-content/plugins/klaro-geo /var/www/html/wp-content/plugins/geoip-detect 2>/dev/null || true
chmod -R a+w /var/www/html/wp-content/plugins/klaro-geo /var/www/html/wp-content/plugins/geoip-detect
echo "Permissions set successfully"

# Print memory limits for debugging
echo "PHP Memory Limit: $(php -r 'echo ini_get("memory_limit");')"
echo "WP Memory Limit: $WP_MEMORY_LIMIT"
echo "WP Max Memory Limit: $WP_MAX_MEMORY_LIMIT"

# Start Apache in foreground
echo "Starting Apache..."
exec docker-entrypoint.sh apache2-foreground