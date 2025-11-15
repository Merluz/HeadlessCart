#!/bin/bash

set -e

PLUGIN="headlesscart"
VERSION="1.0.0"
BUILD_DIR="build/$PLUGIN"

echo "Building $PLUGIN v$VERSION..."

# Clean previous build
rm -rf build
mkdir -p "$BUILD_DIR"

# Copy required plugin files
rsync -av --exclude='vendor/bin' \
          --exclude='.git' \
          --exclude='.github' \
          --exclude='node_modules' \
          --exclude='tests' \
          --exclude='*.md' \
          --exclude='composer.*' \
          ./ "$BUILD_DIR/"

# Create zip
cd build
zip -r "${PLUGIN}-${VERSION}.zip" "$PLUGIN"

echo "DONE → build/${PLUGIN}-${VERSION}.zip"
