#!/bin/bash

# This script copies the debug log from the WordPress test container to the host
# Note: The WordPress dev container writes its logs directly to the logs directory

# Make sure the logs directory exists
mkdir -p ${PWD}/logs

# Copy debug log from wordpress_test container
if docker ps | grep -q wordpress_test; then
  echo "Copying debug log from wordpress_test container..."
  docker exec wordpress_test bash -c "cat /var/www/html/wp-content/debug.log" > ${PWD}/logs/debug_test.log
  echo "Debug log copied to ${PWD}/logs/debug_test.log"
else
  echo "WordPress test container is not running"
fi

# Note about wordpress (dev) container logs
echo "Note: The WordPress dev container writes its logs directly to ${PWD}/logs/debug_dev.log"
if ! docker ps | grep -q wordpress; then
  echo "WordPress container is not running"
fi