FROM php:8.2-apache

# Install required packages
RUN apt-get update && apt-get install -y \
    libssl-dev \
    ca-certificates \
    openssl \
    pkg-config \
    unzip \
    && update-ca-certificates \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install mysqli \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Application directory
WORKDIR /var/www/html

# Copy project
COPY . /var/www/html/

# Install PHP dependencies
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# PHP production settings
RUN printf '%s\n' \
    'display_errors=Off' \
    'log_errors=On' \
    'error_log=/proc/self/fd/2' \
    > /usr/local/etc/php/conf.d/production.ini

# Enable Apache rewrite
RUN a2enmod rewrite

# Render port
ENV PORT=10000

EXPOSE 10000

CMD ["sh", "-c", "sed -i \"s/^Listen 80$/Listen ${PORT:-10000}/\" /etc/apache2/ports.conf && sed -i \"s/\\*:80/\\*:${PORT:-10000}/g\" /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]