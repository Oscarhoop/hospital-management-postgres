# Use official PHP runtime
FROM php:8.2-cli

# Enable PDO SQLite extension for the hospital DB
RUN docker-php-ext-install pdo_sqlite

# Install Composer (optional but handy if composer.json appears later)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

# Copy app source
COPY . /app

# Install PHP dependencies if composer.json exists
RUN if [ -f composer.json ]; then composer install --no-dev --prefer-dist; fi

# Default port Render provides via $PORT
ENV PORT=10000

EXPOSE 10000

# Start PHP's built-in server using router.php for routing
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} router.php"]
