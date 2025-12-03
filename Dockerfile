# Use official PHP runtime
FROM php:8.2-cli

# Enable PDO SQLite extension for the hospital DB
RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

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

# Ensure the SQLite directory exists, initialize the DB, run M-Pesa migrations, then start PHP server
CMD ["sh", "-c", "mkdir -p $(dirname ${DB_PATH:-/app/backend/data/database.sqlite}) && php backend/init_db.php && php backend/migrations/add_mpesa_tables.php && php -S 0.0.0.0:${PORT} router.php"]
