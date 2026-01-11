FROM php:8.2-cli-alpine

RUN apk add --no-cache \
    git \
    unzip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* ./

RUN composer install --no-dev --optimize-autoloader --no-scripts || true

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN chmod +x bin/console

ENTRYPOINT ["php", "bin/console"]
