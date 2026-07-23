# Use the official PHP 8.4 image with Apache
FROM php:8.4-apache

# Install system dependencies and PHP extensions needed by Laravel
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring xml bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite (required for Laravel routing)
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy all project files into the container
COPY . .

# Install PHP dependencies (no dev packages in production)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set correct permissions on storage and cache folders
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Point Apache to Laravel's public folder (not the root)
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess files (required for Laravel's URL routing)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copy the Laravel .htaccess into public if it doesn't exist
RUN if [ ! -f /var/www/html/public/.htaccess ]; then \
    echo "RewriteEngine On\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteRule ^ index.php [L]" > /var/www/html/public/.htaccess; \
    fi

# Run migrations and start Apache when container starts
CMD php artisan config:clear && \
    php artisan migrate --force && \
    php artisan db:seed --force && \
    apache2-foreground

# Expose port 80 (Apache's default)
EXPOSE 80
