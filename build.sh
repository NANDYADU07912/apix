#!/bin/bash
set -e

echo "========================================="
echo "Building YouTube MP3 API for Render"
echo "========================================="

echo ""
echo "📦 Installing system dependencies..."
apt-get update
apt-get install -y --no-install-recommends python3-pip ffmpeg

echo ""
echo "🐍 Installing Python packages..."
pip install --break-system-packages --no-cache-dir yt-dlp

echo ""
echo "📁 Setting up directories..."
mkdir -p downloads
chmod 777 downloads

echo ""
echo "✅ Build completed successfully!"
echo "========================================="
