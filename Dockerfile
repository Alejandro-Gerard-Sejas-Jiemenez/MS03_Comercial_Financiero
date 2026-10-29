# MS-03 Comercial y Financiero.
#   docker build -t ms03 .
# El mismo contenedor sirve para la API, el scheduler y el consumidor de eventos:
# solo cambia el comando (ver docker-compose.yml).

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts \
    --prefer-dist --optimize-autoloader --ignore-platform-req=ext-sockets

FROM php:8.2-cli-alpine
RUN apk add --no-cache libpq postgresql-dev \
    && docker-php-ext-install pdo_pgsql bcmath sockets pcntl \
    && apk del postgresql-dev

WORKDIR /var/www
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 8000
HEALTHCHECK --interval=15s --timeout=3s --retries=5 \
    CMD php -r "exit(@file_get_contents('http://127.0.0.1:8000/api/v1/salud') ? 0 : 1);"

# Migra y sirve. APP_KEY, DB_* y KEYCLOAK_* llegan por variables de entorno.
CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]
