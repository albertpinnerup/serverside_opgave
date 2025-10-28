# Use the official PHP image with Apache
FROM php:8.2-apache

# Install PDO and the MySQL driver for PHP
RUN docker-php-ext-install pdo pdo_mysql


# Copy your app into the web root (only for reference — docker-compose overrides this volume)
# COPY ./public /var/www/html
