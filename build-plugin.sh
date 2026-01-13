#!/bin/bash

# Klaro Geo WordPress Plugin Build Script
# Creates a production-ready zip file excluding development files

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Klaro Geo Plugin Build Script${NC}"
echo -e "${GREEN}========================================${NC}"

# Get plugin version from main PHP file
VERSION=$(grep "Version:" klaro-geo.php | head -1 | sed 's/.*Version:[[:space:]]*\([0-9.]*\).*/\1/')
PLUGIN_NAME="klaro-geo"
BUILD_DIR="build"
PLUGIN_DIR="${BUILD_DIR}/${PLUGIN_NAME}"
ZIP_NAME="${PLUGIN_NAME}-${VERSION}.zip"

echo -e "\n${YELLOW}Plugin Version:${NC} ${VERSION}"
echo -e "${YELLOW}Output File:${NC} ${ZIP_NAME}\n"

# Clean up any existing build directory
if [ -d "$BUILD_DIR" ]; then
    echo -e "${YELLOW}Cleaning up old build directory...${NC}"
    rm -rf "$BUILD_DIR"
fi

# Create build directory structure
echo -e "${YELLOW}Creating build directory...${NC}"
mkdir -p "$PLUGIN_DIR"

# Copy main plugin files
echo -e "${YELLOW}Copying plugin files...${NC}"
cp klaro-geo.php "$PLUGIN_DIR/"
cp klaro.js "$PLUGIN_DIR/"
cp klaro.css "$PLUGIN_DIR/"
cp klaro-config.js "$PLUGIN_DIR/"
cp countries.csv "$PLUGIN_DIR/"
cp index.php "$PLUGIN_DIR/"
cp readme.md "$PLUGIN_DIR/"

# Copy directories (production files only)
echo -e "${YELLOW}Copying includes/...${NC}"
cp -r includes "$PLUGIN_DIR/"

echo -e "${YELLOW}Copying js/...${NC}"
cp -r js "$PLUGIN_DIR/"

echo -e "${YELLOW}Copying css/...${NC}"
cp -r css "$PLUGIN_DIR/"

# Copy assets directory if it exists (for readme images, etc.)
if [ -d "assets" ]; then
    echo -e "${YELLOW}Copying assets/...${NC}"
    cp -r assets "$PLUGIN_DIR/"
fi

# Optional: Copy vendor if you have production Composer dependencies
# Uncomment if needed:
# if [ -d "vendor" ]; then
#     echo -e "${YELLOW}Copying vendor/ (production dependencies)...${NC}"
#     cp -r vendor "$PLUGIN_DIR/"
# fi

# Clean up any development files that might have been copied
echo -e "${YELLOW}Removing development files from build...${NC}"
find "$PLUGIN_DIR" -type f -name "*.test.js" -delete
find "$PLUGIN_DIR" -type f -name "*.spec.js" -delete
find "$PLUGIN_DIR" -type f -name ".DS_Store" -delete
find "$PLUGIN_DIR" -type d -name ".git" -exec rm -rf {} + 2>/dev/null || true
find "$PLUGIN_DIR" -type d -name "__tests__" -exec rm -rf {} + 2>/dev/null || true

# Create zip file
echo -e "${YELLOW}Creating zip file: ${ZIP_NAME}...${NC}"
cd "$BUILD_DIR"
zip -r "../${ZIP_NAME}" "${PLUGIN_NAME}" -q

# Return to original directory
cd ..

# Get zip file size
ZIP_SIZE=$(du -h "${ZIP_NAME}" | cut -f1)

# Success message
echo -e "\n${GREEN}========================================${NC}"
echo -e "${GREEN}✓ Build Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Output:${NC} ${ZIP_NAME}"
echo -e "${GREEN}Size:${NC} ${ZIP_SIZE}"
echo -e "${GREEN}Location:${NC} $(pwd)/${ZIP_NAME}"
echo -e "\n${YELLOW}Next steps:${NC}"
echo -e "  1. Test the plugin by uploading ${ZIP_NAME} to WordPress"
echo -e "  2. Verify all functionality works correctly"
echo -e "  3. Clean up build directory: ${GREEN}rm -rf ${BUILD_DIR}${NC}"
echo -e "\n${YELLOW}Files excluded from build:${NC}"
echo -e "  • docker/, tests/, e2e/, e2e-report/, coverage/, test-results/"
echo -e "  • node_modules/, vendor/ (optional)"
echo -e "  • Development docs: readme-dev.md, E2E-*.md, etc."
echo -e "  • Config files: package.json, composer.json, phpunit.xml, etc."
echo -e "  • Git files and IDE settings"
echo -e "\n"
