# Use official PHP + Apache image
FROM php:8.1-apache

# Install extensions for MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy your project files into Apache document root
COPY . /var/www/html/

# Expose Apache port
EXPOSE 80
