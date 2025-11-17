#!/bin/bash

# _edit CMS Distribution Builder
# Creates a production-ready archive file

set -e  # Exit on error

echo "🔨 Building _edit CMS distribution..."
echo ""

# Get version from user or use default
VERSION=${1:-"dev"}
OUTPUT_DIR="dist"
OUTPUT_FILE="${OUTPUT_DIR}/_edit-${VERSION}.zip"

# Step 1: Build distribution directory
echo "📦 Preparing distribution..."
TEMP_DIR=$(mktemp -d)
DIST_ROOT="${TEMP_DIR}/_edit"

# Create directory structure
mkdir -p "${DIST_ROOT}"
mkdir -p "${DIST_ROOT}/data/database"
mkdir -p "${DIST_ROOT}/data/config/modules"
mkdir -p "${DIST_ROOT}/data/config/field-groups"
mkdir -p "${DIST_ROOT}/data/config/blocks"
mkdir -p "${DIST_ROOT}/uploads"

# Step 2: Build Vue frontend into admin/
echo "📦 Building Vue frontend..."
cd _edit/admin-source

npm install --silent
npx vite build --outDir "${DIST_ROOT}/admin"

cd ../..

# Copy PUBLIC-API.md to admin directory
cp _edit/admin/PUBLIC-API.md "${DIST_ROOT}/admin/"

# Step 3: Copy PHP backend
echo "  → Copying PHP backend (core, admin-api, api)..."
cp -r _edit/core "${DIST_ROOT}/"
cp -r _edit/admin-api "${DIST_ROOT}/"
cp -r _edit/api "${DIST_ROOT}/"

# Step 4: Copy .htaccess files
echo "  → Copying .htaccess files..."
cp _edit/admin/.htaccess "${DIST_ROOT}/admin/"
cp _edit/admin-api/.htaccess "${DIST_ROOT}/admin-api/"
cp _edit/api/.htaccess "${DIST_ROOT}/api/"
cp _edit/core/.htaccess "${DIST_ROOT}/core/"
cp _edit/data/.htaccess "${DIST_ROOT}/data/"
cp _edit/uploads/.htaccess "${DIST_ROOT}/uploads/"

# Copy root files
echo "  → Copying root files..."
cp _edit/index.html "${DIST_ROOT}/"
cp _edit/.htaccess "${DIST_ROOT}/"
cp _edit/.user.ini "${DIST_ROOT}/"
cp _edit/nginx.conf "${DIST_ROOT}/"

# Step 4.5: Generate .htaccess integrity hashes and inject into health.php
echo "  → Generating .htaccess integrity hashes..."
HASH_ROOT=$(sha256sum "${DIST_ROOT}/.htaccess" | cut -d' ' -f1)
HASH_ADMIN=$(sha256sum "${DIST_ROOT}/admin/.htaccess" | cut -d' ' -f1)
HASH_ADMIN_API=$(sha256sum "${DIST_ROOT}/admin-api/.htaccess" | cut -d' ' -f1)
HASH_API=$(sha256sum "${DIST_ROOT}/api/.htaccess" | cut -d' ' -f1)
HASH_CORE=$(sha256sum "${DIST_ROOT}/core/.htaccess" | cut -d' ' -f1)
HASH_DATA=$(sha256sum "${DIST_ROOT}/data/.htaccess" | cut -d' ' -f1)
HASH_UPLOADS=$(sha256sum "${DIST_ROOT}/uploads/.htaccess" | cut -d' ' -f1)

# Replace placeholders in health.php
sed -i.bak \
  -e "s/{{HASH_ROOT}}/${HASH_ROOT}/g" \
  -e "s/{{HASH_ADMIN}}/${HASH_ADMIN}/g" \
  -e "s/{{HASH_ADMIN_API}}/${HASH_ADMIN_API}/g" \
  -e "s/{{HASH_API}}/${HASH_API}/g" \
  -e "s/{{HASH_CORE}}/${HASH_CORE}/g" \
  -e "s/{{HASH_DATA}}/${HASH_DATA}/g" \
  -e "s/{{HASH_UPLOADS}}/${HASH_UPLOADS}/g" \
  "${DIST_ROOT}/api/routes/health.php"

# Remove backup file created by sed
rm -f "${DIST_ROOT}/api/routes/health.php.bak"

# Create .gitkeep files for empty directories
touch "${DIST_ROOT}/uploads/.gitkeep"
touch "${DIST_ROOT}/data/database/.gitkeep"

# Create a README for the data directory
cat > "${DIST_ROOT}/data/README.txt" << 'EOF'
This directory contains all user data and configuration.

DO NOT DELETE this folder when updating - it contains:
- Your database (data/database/site.sqlite)
- Your post types and field groups (data/config/)

When updating _edit CMS:
1. Backup this entire 'data' folder
2. Delete the 'admin' folder
3. Extract the new 'admin' folder from the update
4. Done!
EOF

echo "✓ Files prepared"
echo ""

# Step 3: Create archive file
echo "📦 Creating distribution archive..."
ORIGINAL_DIR=$(pwd)
mkdir -p "${ORIGINAL_DIR}/${OUTPUT_DIR}"

cd "${TEMP_DIR}"
zip -r -q "${ORIGINAL_DIR}/${OUTPUT_FILE}" _edit
cd "${ORIGINAL_DIR}"

# Cleanup
rm -rf "${TEMP_DIR}"

# Get file size
FILE_SIZE=$(du -h "${OUTPUT_FILE}" | cut -f1)

echo "✓ Distribution created"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✨ Build complete!"
echo ""
echo "   File: ${OUTPUT_FILE}"
echo "   Size: ${FILE_SIZE}"
echo ""
echo "Distribution includes:"
echo "  • admin/ - Vue SPA admin interface"
echo "  • admin-api/ - Admin API (requires auth)"
echo "  • api/ - Public API (no auth required)"
echo "  • core/ - Shared PHP classes"
echo "  • data/ - User data (protected)"
echo "  • uploads/ - Media files (public)"
echo "  • .htaccess files in each directory"
echo ""
echo "Ready to deploy! 🚀"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
