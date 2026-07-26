# Use the official PHP 8.4 image with Apache
FROM php:8.4-apache

# Install system dependencies, Node.js, and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    nodejs \
    npm \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring xml bcmath opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Production OPcache settings (validate_timestamps=0: code never changes at runtime)
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache-production.ini

# Enable Apache mod_rewrite (required for Laravel routing)
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy all project files into the container
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Node dependencies and build frontend assets
RUN npm ci && npm run build && rm -rf node_modules

# Set correct permissions on storage and cache folders
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Point Apache to Laravel's public folder (not the root)
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess files (required for Laravel's URL routing)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Cache config/views, run migrations (seed only when RUN_SEED=true), start Apache
CMD php artisan config:cache && \
    php artisan view:cache && \
    php artisan migrate --force && \
    if [ "$RUN_SEED" = "true" ]; then php artisan db:seed --force; fi && \
    apache2-foreground

# Expose port 80 (Apache's default)
EXPOSE 80
