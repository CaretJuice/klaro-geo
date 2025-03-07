#!/bin/bash
# Make this script executable with: chmod +x rebuild-and-test.sh

# Stop any running containers
echo "Stopping any running containers..."
docker-compose down

# Remove the vendor volume to ensure a clean rebuild
echo "Removing vendor volume..."
docker volume rm docker_klaro_vendor 2>/dev/null || true

# Rebuild the test container
echo "Rebuilding test container..."
docker-compose build wordpress_test

# Run the tests
echo "Running tests..."
docker-compose run wordpress_test

# Show the logs
echo "Test logs:"
cat logs/debug_test.log 2>/dev/null || echo "No test logs found"