#!/bin/bash

# This script runs JavaScript tests directly in a single Docker command
# without relying on the existing npm cache or entrypoint script

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

# Check if a specific test was specified
TEST_FILTER=""
if [ -n "$1" ]; then
  echo "Running specific JavaScript test: $1..."
  TEST_FILTER="-t \"$1\""
else
  echo "Running all JavaScript tests directly..."
fi

# Create logs directory if it doesn't exist
mkdir -p docker/logs

# Run tests in a single Docker command
docker run --rm \
  --network docker_default \
  -v "$PWD:/var/www/html/wp-content/plugins/klaro-geo" \
  -v "$PWD/docker/logs:/logs" \
  -w /var/www/html/wp-content/plugins/klaro-geo \
  wordpress:latest \
  bash -c "
    # Install Node.js and npm
    apt-get update && apt-get install -y nodejs npm
    
    # Set up temporary directories with full permissions
    mkdir -p /tmp/npm-cache
    chmod -R 777 /tmp/npm-cache
    
    # Configure npm to use the temporary cache
    npm config set cache /tmp/npm-cache
    
    # Install dependencies and run tests
    npm install
    
    # Run tests and save output
    echo 'Running tests...'
    if [ -n "$TEST_FILTER" ]; then
      npm test -- $TEST_FILTER | tee /logs/jest-output.log
    else
      npm test | tee /logs/jest-output.log
    fi
    
    # Copy npm logs if they exist
    if [ -d /tmp/npm-cache/_logs ]; then
      mkdir -p /logs/npm
      cp -r /tmp/npm-cache/_logs/* /logs/npm/
      echo 'npm logs copied to logs directory'
    else
      echo 'No npm logs found'
      mkdir -p /logs/npm
      echo 'No npm logs found' > /logs/npm/info.log
    fi
    
    echo 'Tests completed!'
  "

# Display the test results
echo ""
echo "=== Test Results ==="
if [ -f "docker/logs/jest-output.log" ]; then
  cat docker/logs/jest-output.log
else
  echo "No test output found"
fi

echo ""
echo "=== npm Logs ==="
if [ -d "docker/logs/npm" ]; then
  for log in docker/logs/npm/*; do
    if [ -f "$log" ]; then
      echo "--- $log ---"
      cat "$log"
      echo ""
    fi
  done
else
  echo "No npm logs found"
fi

echo ""
echo "All logs are available in the docker/logs directory"