#!/bin/bash

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Detect OS and set UID/GID appropriately
case "$(uname -s)" in
    Linux|Darwin)
        # Linux and macOS: Use actual user/group ID
        if command_exists id; then
            export DOCKER_UID=$(id -u)
            export DOCKER_GID=$(id -g)
        else
            # Fallback to default values
            export UID=1000
            export GID=1000
        fi
        ;;
    MINGW*|CYGWIN*|MSYS*)
        # Windows: Use default values
            export DOCKER_UID=$(id -u)
            export DOCKER_GID=$(id -g)
        ;;
    *)
        echo "Unknown operating system"
        export UID=1000
        export GID=1000
        ;;
esac

# Check which docker compose command to use
if command_exists docker-compose; then
    DOCKER_COMPOSE="docker-compose"
elif docker compose version >/dev/null 2>&1; then
    DOCKER_COMPOSE="docker compose"
else
    echo "Neither docker-compose nor docker compose is available"
    exit 1
fi

# Change to the docker directory
cd "$(dirname "$0")/docker"

# Start the development environment
$DOCKER_COMPOSE up -d