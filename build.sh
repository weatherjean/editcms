#!/bin/bash

# _edit CMS Distribution Builder
# Creates a production-ready archive file

set -e  # Exit on error

echo "🔨 Building _edit CMS distribution..."
echo ""

# Get version from user or use default
VERSION=${1:-"dev"}
OUTPUT_DIR="dist"
OUTPUT_FILE="${OUTPUT_DIR}/_edit-${VERSION}.tar.gz"

# Step 1: Build Vue frontend
echo "📦 Building Vue frontend..."
cd _edit/admin-source
npm install --silent
npm run build
cd ../..
echo "✓ Frontend built"
echo ""

# Step 2: Prepare distribution directory
echo "🗂️  Preparing distribution files..."
TEMP_DIR=$(mktemp -d)
DIST_ROOT="${TEMP_DIR}/_edit"

# Create directory structure
mkdir -p "${DIST_ROOT}/admin/core"
mkdir -p "${DIST_ROOT}/admin/api"
mkdir -p "${DIST_ROOT}/admin/dist"
mkdir -p "${DIST_ROOT}/data/database"
mkdir -p "${DIST_ROOT}/data/config/modules"
mkdir -p "${DIST_ROOT}/data/config/field-groups"
mkdir -p "${DIST_ROOT}/data/config/blocks"
mkdir -p "${DIST_ROOT}/uploads"

# Copy admin files
echo "  → Copying admin/core/..."
cp -r _edit/admin/core/* "${DIST_ROOT}/admin/core/"

echo "  → Copying admin/api/..."
cp -r _edit/admin/api/* "${DIST_ROOT}/admin/api/"

echo "  → Copying admin/dist/..."
cp -r _edit/admin/dist/* "${DIST_ROOT}/admin/dist/"

# Copy root files
echo "  → Copying configuration files..."
cp _edit/.htaccess "${DIST_ROOT}/"
cp _edit/nginx.conf "${DIST_ROOT}/"
cp DEPLOYMENT.md "${DIST_ROOT}/"

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
tar -czf "${ORIGINAL_DIR}/${OUTPUT_FILE}" _edit
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
echo "  • admin/ folder (PHP backend + built frontend)"
echo "  • data/ folder (empty, ready for user data)"
echo "  • uploads/ folder (empty, ready for media)"
echo "  • .htaccess, nginx.conf, DEPLOYMENT.md"
echo ""
echo "Ready to deploy! 🚀"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
