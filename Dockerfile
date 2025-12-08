# Use official PHP runtime
FROM php:8.2-cli

# Enable PDO PostgreSQL extension
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
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

CMD ["php", "backend/bootstrap.php"]
