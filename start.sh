#!/bin/bash

echo "Starting YouTube MP3 API..."

# Ensure downloads directory exists
mkdir -p downloads
chmod 777 downloads

# Get port from environment or use default
PORT=${PORT:-5000}

echo "API running on http://0.0.0.0:$PORT"
echo "Documentation: http://0.0.0.0:$PORT/docs.html"
echo ""

# Start PHP server
php -S 0.0.0.0:$PORT
