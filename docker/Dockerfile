# ORSEE 3.4.0 image for the BEER Lab (local testing & VPS deployment).
# PHP 8.2 + Apache with all extensions ORSEE requires.
FROM php:8.2-apache

# System libs needed to build the PHP extensions (gd, curl, mbstring, mysql).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libcurl4-openssl-dev \
        libssl-dev \
        default-mysql-client \
        cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd mbstring curl \
    && rm -rf /var/lib/apt/lists/*

# Sensible PHP settings for ORSEE (uploads, memory, timezone default).
RUN { \
        echo "memory_limit=256M"; \
        echo "upload_max_filesize=32M"; \
        echo "post_max_size=32M"; \
        echo "date.timezone=America/Monterrey"; \
        echo "expose_php=off"; \
    } > /usr/local/etc/php/conf.d/orsee.ini

# Apache: enable rewrite/headers, install our vhost (docroot -> orsee/, hardening).
RUN a2enmod rewrite headers
COPY docker/000-orsee.conf /etc/apache2/sites-available/000-default.conf

# App code. The whole repo is copied; Apache serves from /var/www/html/orsee.
COPY . /var/www/html/

# The webalizer/usage dir must be writable by the web/cron user.
RUN chown -R www-data:www-data /var/www/html/orsee/usage \
    && chmod +x /var/www/html/bin/cron.sh

WORKDIR /var/www/html
