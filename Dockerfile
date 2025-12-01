FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    python3-pip \
    ffmpeg \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install yt-dlp
RUN pip install --break-system-packages --no-cache-dir yt-dlp

# Copy application files
WORKDIR /var/www/html
COPY . .

# Create downloads directory and set permissions
RUN mkdir -p downloads && chmod 777 downloads

# Enable PHP built-in server or Apache
EXPOSE 5000

# Use PHP built-in server for Render
CMD ["php", "-S", "0.0.0.0:5000"]
