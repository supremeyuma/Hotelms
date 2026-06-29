FROM node:22-alpine AS frontend
WORKDIR /app
RUN apk add --no-cache libc6-compat
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.2-fpm AS base

ARG APP_ENV=production
ENV APP_ENV=${APP_ENV}

RUN apt-get update && apt-get install -y \
    curl \
    unzip \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libssl-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mbstring \
    bcmath \
    ctype \
    exif \
    fileinfo \
    gd \
    intl \
    pcntl \
    tokenizer \
    xml \
    zip

RUN pecl install redis && docker-php-ext-enable redis
RUN docker-php-ext-enable opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN addgroup --system --gid 1000 app && \
    adduser --system --uid 1000 --ingroup app --shell /bin/sh --disabled-password app

FROM base AS php

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --no-scripts

COPY --from=frontend /app/public/build ./public/build
COPY . .

RUN composer dump-autoload \
    --optimize \
    --no-dev

RUN mkdir -p storage/logs \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/public \
    bootstrap/cache

RUN chown -R app:app /var/www/html && \
    find storage bootstrap/cache -type d -exec chmod 775 {} \; && \
    find storage bootstrap/cache -type f -exec chmod 664 {} \;

COPY docker/php/php.ini $PHP_INI_DIR/conf.d/app-php.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY docker/healthcheck.sh /usr/local/bin/docker-healthcheck.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh && \
    chmod +x /usr/local/bin/docker-healthcheck.sh

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=3 \
    CMD /usr/local/bin/docker-healthcheck.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["web"]

FROM nginx:stable-alpine AS nginx

RUN apk add --no-cache wget

COPY --from=php /var/www/html/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=3 \
    CMD wget -qO- http://localhost/up || exit 1
