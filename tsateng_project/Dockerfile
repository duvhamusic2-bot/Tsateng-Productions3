# Use official PHP Apache image
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd

# Enable Apache modules
RUN a2enmod rewrite headers

# Copy all project files to the web root
# The current directory (where Dockerfile is) is the build context.
# We copy everything *except* the Dockerfile itself and the crs/ folder contents
# which are now at the root level, but the original Dockerfile was expecting
# public/ and src/. We will copy the entire project folder contents.
COPY . /var/www/html/

# The crs/ folder contains PHP files that should be accessible.
# The original structure had public/ and src/. Now everything is flat.
# We will ensure the crs/ folder is copied correctly.

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Switch to port 8080 for Cloud Run
# Cloud Run requires the application to listen on the port specified by the PORT environment variable,
# which defaults to 8080. The original Dockerfile already handles this for Apache.
RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf \
    && sed -i 's/80/8080/g' /etc/apache2/sites-available/000-default.conf

# Create a simple health check file
RUN echo "<?php header('Content-Type: text/plain'); echo 'OK'; ?>" > /var/www/html/healthz.php

EXPOSE 8080

# Start Apache
CMD ["apache2-foreground"]
