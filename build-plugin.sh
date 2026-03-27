#!/bin/bash

# Klaro Geo WordPress Plugin Build Script
# Creates two production-ready zip files:
#   1. klaro-geo-{VERSION}.zip       — WordPress.org (callbacks stripped)
#   2. klaro-geo-{VERSION}-full.zip  — GitHub (full featured)

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Strip callback code from build directory for WordPress.org version
strip_callbacks() {
    local dir="$1"

    echo -e "${YELLOW}Stripping callback code for WordPress.org build...${NC}"

    # Files with KLARO_CALLBACKS_START/END markers
    local marker_files=(
        "$dir/includes/admin/klaro-geo-admin-services.php"
        "$dir/includes/class-klaro-geo-service-settings.php"
        "$dir/js/klaro-geo-admin-services.js"
        "$dir/includes/klaro-geo-defaults.php"
    )

    for file in "${marker_files[@]}"; do
        if [ -f "$file" ]; then
            sed -i '/KLARO_CALLBACKS_START/,/KLARO_CALLBACKS_END/d' "$file"
            echo -e "  Stripped markers: $(basename "$file")"
        fi
    done

    # Replace config.php callback lookups with empty strings
    local config_file="$dir/includes/klaro-geo-config.php"
    if [ -f "$config_file" ]; then
        sed -i "s|'onInit' => isset(\$service\['callback'\]\['onInit'\]).*|'onInit' => '',|" "$config_file"
        sed -i "s|'onAccept' => isset(\$service\['callback'\]\['onAccept'\]).*|'onAccept' => '',|" "$config_file"
        sed -i "s|'onDecline' => isset(\$service\['callback'\]\['onDecline'\]).*|'onDecline' => '',|" "$config_file"
        echo -e "  Replaced callbacks: klaro-geo-config.php"
    fi
}

# Verify callback stripping was successful
verify_stripping() {
    local dir="$1"

    echo -e "${YELLOW}Verifying callback stripping...${NC}"

    # Check no markers remain
    local markers
    markers=$(grep -rl "KLARO_CALLBACKS" "$dir" --include="*.php" --include="*.js" 2>/dev/null || true)
    if [ -n "$markers" ]; then
        echo -e "${RED}ERROR: Marker comments still present in:${NC}"
        echo "$markers"
        exit 1
    fi

    # Check config replacement happened
    local config_file="$dir/includes/klaro-geo-config.php"
    if ! grep -q "'onInit' => ''," "$config_file"; then
        echo -e "${RED}ERROR: Config callback replacement failed${NC}"
        exit 1
    fi

    # Check callback textareas are gone from admin UI
    if grep -q "service_oninit" "$dir/includes/admin/klaro-geo-admin-services.php"; then
        echo -e "${RED}ERROR: Callback form fields still present in admin services${NC}"
        exit 1
    fi

    echo -e "${GREEN}  Verification passed${NC}"
}

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Klaro Geo Plugin Build Script${NC}"
echo -e "${GREEN}========================================${NC}"

# Get plugin version from main PHP file
VERSION=$(grep "Version:" klaro-geo.php | head -1 | sed 's/.*Version:[[:space:]]*\([0-9.]*\).*/\1/')
PLUGIN_NAME="klaro-geo"
BUILD_DIR="build"
PLUGIN_DIR="${BUILD_DIR}/${PLUGIN_NAME}"
ZIP_NAME="${PLUGIN_NAME}-${VERSION}.zip"
FULL_ZIP_NAME="${PLUGIN_NAME}-${VERSION}-full.zip"

echo -e "\n${YELLOW}Plugin Version:${NC} ${VERSION}"
echo -e "${YELLOW}WordPress.org:${NC} ${ZIP_NAME}"
echo -e "${YELLOW}GitHub (full):${NC} ${FULL_ZIP_NAME}\n"

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
cp countries.csv "$PLUGIN_DIR/"
cp subdivisions.csv "$PLUGIN_DIR/"
cp index.php "$PLUGIN_DIR/"
cp readme.md "$PLUGIN_DIR/"
cp readme.txt "$PLUGIN_DIR/"
cp LICENSE "$PLUGIN_DIR/"

# Copy languages directory for i18n
if [ -d "languages" ]; then
    echo -e "${YELLOW}Copying languages/...${NC}"
    cp -r languages "$PLUGIN_DIR/"
else
    mkdir -p "$PLUGIN_DIR/languages"
fi

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

# Clean up any development files that might have been copied
echo -e "${YELLOW}Removing development files from build...${NC}"
find "$PLUGIN_DIR" -type f -name "*.test.js" -delete
find "$PLUGIN_DIR" -type f -name "*.spec.js" -delete
find "$PLUGIN_DIR" -type f -name ".DS_Store" -delete
find "$PLUGIN_DIR" -type d -name ".git" -exec rm -rf {} + 2>/dev/null || true
find "$PLUGIN_DIR" -type d -name "__tests__" -exec rm -rf {} + 2>/dev/null || true

# ===== Create FULL version zip (GitHub) =====
echo -e "\n${YELLOW}Creating full version zip: ${FULL_ZIP_NAME}...${NC}"
cd "$BUILD_DIR"
zip -r "../${FULL_ZIP_NAME}" "${PLUGIN_NAME}" -q
cd ..

# ===== Strip callbacks and create WordPress.org version =====
strip_callbacks "$PLUGIN_DIR"
verify_stripping "$PLUGIN_DIR"

echo -e "${YELLOW}Creating WordPress.org zip: ${ZIP_NAME}...${NC}"
cd "$BUILD_DIR"
zip -r "../${ZIP_NAME}" "${PLUGIN_NAME}" -q
cd ..

# Get zip file sizes
ZIP_SIZE=$(du -h "${ZIP_NAME}" | cut -f1)
FULL_ZIP_SIZE=$(du -h "${FULL_ZIP_NAME}" | cut -f1)

# Success message
echo -e "\n${GREEN}========================================${NC}"
echo -e "${GREEN}Build Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}WordPress.org:${NC} ${ZIP_NAME} (${ZIP_SIZE})"
echo -e "${GREEN}GitHub (full):${NC} ${FULL_ZIP_NAME} (${FULL_ZIP_SIZE})"
echo -e "\n${YELLOW}Next steps:${NC}"
echo -e "  1. Test both zips by uploading to WordPress"
echo -e "  2. Upload ${ZIP_NAME} to WordPress.org"
echo -e "  3. Attach ${FULL_ZIP_NAME} to GitHub release"
echo -e "  4. Clean up: ${GREEN}rm -rf ${BUILD_DIR}${NC}"
echo -e "\n"
