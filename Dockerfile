FROM php:8.2-apache

# Install system dependencies + PHP extensions in one layer
RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    unzip \
    curl \
    && docker-php-ext-install mysqli pdo pdo_mysql zip \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin \
    --filename=composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first to leverage Docker layer caching
COPY composer.json composer.lock ./

# Install dependencies (cached unless composer files change)
RUN composer install --no-dev --optimize-autoloader

# Copy the rest of the project files
COPY . .

# Copy CA certificate for secure MySQL connection
COPY certs/ca.pem /certs/ca.pem

# Apache custom configuration
COPY apache.conf /etc/apache2/conf-available/custom.conf
RUN a2enconf custom

# Permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port
EXPOSE 8080

# Start Apache server
CMD ["apache2-foreground"]