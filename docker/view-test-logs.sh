#!/bin/bash

# This script displays the test logs from the Docker container
# Run it with: ./docker/view-test-logs.sh

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

echo "Viewing test logs from the Docker container..."

# Check if logs directory exists
if [ ! -d "logs" ]; then
  mkdir -p logs
  echo "Created logs directory"
fi

# Copy logs from the container to the host
docker compose run --user root --entrypoint bash wordpress_test -c "
  # Copy WordPress debug log if it exists
  if [ -f /var/www/html/wp-content/debug.log ]; then
    cat /var/www/html/wp-content/debug.log > /var/www/html/wp-content/plugins/klaro-geo/docker/logs/debug_test.log
    echo 'WordPress debug log copied to logs/debug_test.log'
  else
    echo 'WordPress debug log not found' > /var/www/html/wp-content/plugins/klaro-geo/docker/logs/debug_test.log
  fi
  
  # Copy npm logs if they exist
  if [ -d /tmp/npm-cache/_logs ]; then
    mkdir -p /var/www/html/wp-content/plugins/klaro-geo/docker/logs/npm
    cp -r /tmp/npm-cache/_logs/* /var/www/html/wp-content/plugins/klaro-geo/docker/logs/npm/
    echo 'npm logs copied to logs/npm/'
  else
    echo 'npm logs not found'
    mkdir -p /var/www/html/wp-content/plugins/klaro-geo/docker/logs/npm
    echo 'npm logs not found' > /var/www/html/wp-content/plugins/klaro-geo/docker/logs/npm/info.log
  fi
  
  # Copy Jest output if it exists
  if [ -f /var/www/html/wp-content/plugins/klaro-geo/jest-output.log ]; then
    cp /var/www/html/wp-content/plugins/klaro-geo/jest-output.log /var/www/html/wp-content/plugins/klaro-geo/docker/logs/
    echo 'Jest output copied to logs/jest-output.log'
  else
    echo 'Jest output not found'
  fi
  
  # Set permissions on log files
  chmod -R 777 /var/www/html/wp-content/plugins/klaro-geo/docker/logs
"

# Display the logs
echo "=== WordPress Debug Log ==="
if [ -f "logs/debug_test.log" ]; then
  cat logs/debug_test.log
else
  echo "Debug log not found"
fi

echo ""
echo "=== npm Logs ==="
if [ -d "logs/npm" ]; then
  for log in logs/npm/*; do
    if [ -f "$log" ]; then
      echo "--- $log ---"
      cat "$log"
      echo ""
    fi
  done
else
  echo "npm logs not found"
fi

echo ""
echo "=== Jest Output ==="
if [ -f "logs/jest-output.log" ]; then
  cat logs/jest-output.log
else
  echo "Jest output not found"
fi

echo ""
echo "Logs are available in the docker/logs directory"