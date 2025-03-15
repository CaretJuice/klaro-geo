#!/bin/bash

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

echo "Making scripts executable in $(pwd)..."

# Make all scripts in the docker directory executable
chmod +x *.sh

# Make the main run-tests.sh script executable if it exists
if [ -f "../run-tests.sh" ]; then
    chmod +x ../run-tests.sh
    echo "Made ../run-tests.sh executable"
else
    echo "Warning: ../run-tests.sh not found"
fi

echo "All scripts are now executable!"