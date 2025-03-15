#!/bin/bash

# This script fixes permissions for all shell scripts in the project
# Run it with: bash fix-permissions.sh

echo "Fixing permissions for all shell scripts..."

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

# Make this script executable
chmod +x "$0"

# Make all test scripts executable
for script in run-tests.sh run-js-tests.sh run-php-tests.sh run-js-tests-direct.sh; do
  if [ -f "$script" ]; then
    chmod +x "$script"
    echo "Made $script executable"
  else
    echo "Warning: $script not found"
  fi
done

# Make all scripts in the docker directory executable
if [ -d "docker" ]; then
  find "docker" -name "*.sh" -exec chmod +x {} \;
  echo "Made all scripts in docker/ directory executable"
else
  echo "Warning: docker/ directory not found"
fi

echo "All scripts are now executable!"
echo ""
echo "You can now run:"
echo "  ./run-php-tests.sh        # For PHP tests (no password required)"
echo "  ./run-js-tests.sh         # For all JavaScript tests (no password required)"
echo "  ./run-js-tests.sh testname # For specific JavaScript tests (no password required)"
echo "  ./run-js-tests-direct.sh  # For JavaScript tests in a fresh container (most reliable)"
echo ""
echo "Or use the original scripts (may prompt for password):"
echo "  ./run-tests.sh            # For PHP tests"
echo "  ./run-tests.sh js         # For all JavaScript tests"
echo ""
echo "To fix npm permissions separately:"
echo "  ./docker/fix-npm-permissions.sh"
echo ""
echo "To view test logs:"
echo "  ./docker/view-test-logs.sh"