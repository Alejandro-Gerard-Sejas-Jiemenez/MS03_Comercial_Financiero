#!/usr/bin/env bash
# Levanta la API desde el codigo del arbol de trabajo (puerto 8001) usando el stack de Docker
# (PostgreSQL, RabbitMQ, Keycloak). Sirve para verificar cambios sin reconstruir la imagen.
#   bash scripts/servir-local.sh
#   HUMO_API=http://localhost:8001/api/v1 php scripts/humo.php
cd "$(dirname "$0")/.."

if [ -z "${APP_KEY:-}" ]; then
  APP_KEY="$(php artisan key:generate --show --no-ansi | tr -d '\r\n')"
fi
export APP_KEY
export DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5433 DB_DATABASE="${DB_DATABASE:-ms03_boot}" \
       DB_USERNAME=postgres DB_PASSWORD=postgres DB_SEARCH_PATH=comercial,public \
       COMERCIAL_BROKER_DRIVER=rabbitmq COMERCIAL_WEBHOOK_SECRETO=secreto-e2e CACHE_STORE=file SESSION_DRIVER=array QUEUE_CONNECTION=sync

exec php artisan serve --host=127.0.0.1 --port=8001
