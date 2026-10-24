# MS-03 — Comercial y Financiero · Aloja-Te

Microservicio de pagos, comisiones, liquidaciones, promociones y monetización de la plataforma digital de alojamientos **Aloja-Te** (INF-512, Grupo 6).

**Stack:** PHP 8.2 + Laravel 12 · PostgreSQL 16 · **Clean Architecture**
**Funcionalidades:** F05 (pagos, comisiones y liquidaciones) · F09 (promociones) · F16 (monetización) · F17 (gestión comercial de anfitriones)

| | |
| --- | --- |
| **Casos de uso** | 35, documentados en [`CASOS_DE_USO.md`](CASOS_DE_USO.md) |
| **Tablas** | 19 en el esquema `comercial` |
| **Alcance de esta entrega** | Backend: Dominio, Aplicación e Infraestructura. **Sin capa de Presentación** |
| **Arquitectura de referencia** | `context/MS-03 Comercial y Financiero — Arquitectura Clean (Laravel).md` |

## Cómo correrlo

```bash
cp .env.example .env            # ya apunta a PostgreSQL en el puerto 5433
php artisan key:generate
docker compose up -d            # PostgreSQL 16
php artisan migrate             # 10 migraciones -> 19 tablas en el esquema comercial
```

## Pruebas

```bash
php vendor/bin/phpunit                      # Unit + Application + Architecture (+ Integration si hay PostgreSQL)
php vendor/bin/phpunit --testsuite=Unit     # dominio puro: sin Laravel y sin base de datos
php vendor/bin/phpunit --testsuite=Architecture   # la regla de dependencias como check que rompe el build

# Integración contra PostgreSQL real (se omite sola si no hay base):
DB_CONNECTION=pgsql DB_DATABASE=ms03_comercial DB_HOST=127.0.0.1 DB_PORT=5433 \
  DB_USERNAME=postgres DB_PASSWORD=postgres php vendor/bin/phpunit --testsuite=Integration
```

| Suite | Qué prueba |
| --- | --- |
| `Unit` | Entidades y servicios de dominio, sin Laravel ni base de datos |
| `Application` | Casos de uso con repositorios falsos en memoria |
| `Architecture` | La regla de dependencias y que el contenedor resuelva cada puerto |
| `Integration` | Las restricciones que garantiza PostgreSQL (CHECK, índices únicos parciales, triggers append-only) |
| `Contract` | Eventos y REST compartidos con MS-01, MS-02 y MS-04 — pendiente de acordar los contratos |

## Estructura

```text
├── CASOS_DE_USO.md          los 35 casos de uso, con reglas, eventos y pendientes
├── app/Providers/ComercialServiceProvider.php   enlaza cada interfaz con su implementación
├── config/comercial.php     pasarela, outbox, RabbitMQ, liquidaciones
├── context/                 insumos del proyecto (arquitectura, DDL, diagrama ER)
├── database/
│   ├── migrations/          las 19 tablas del esquema comercial
│   └── support/             restricciones de PostgreSQL que el schema builder no expresa
├── odd/tasks/               bitácora de la implementación
├── src/
│   ├── Shared/              Money, Outbox/Inbox, transacciones, Clock
│   ├── Referencias/         copias locales de MS-01 y MS-02 (read-model por eventos)
│   ├── Monetizacion/        reglas de comisión y cargos (F16)
│   ├── Acuerdos/            acuerdos y métodos de cobro (F17)
│   ├── Promociones/         descuentos y cupones (F09)
│   ├── Pagos/               cobro, reembolsos, comprobantes, conciliación (F05)
│   └── Liquidaciones/       comisiones, liquidaciones, libro contable (F05 + F16)
└── tests/                   Unit · Application · Architecture · Integration · Contract
```

Cada paquete lleva sus propias capas:

```text
src/<Paquete>/
├── Domain/           entidades, objetos de valor, eventos e interfaces de repositorio
├── Application/      casos de uso (Command + Handler) y puertos hacia el exterior
└── Infrastructure/   Eloquent, pasarela de pagos, cifrado, broker
```

**Regla de dependencias:** `Application → Domain ← Infrastructure`. El dominio no importa nada de `Illuminate\*`, y hay una prueba de arquitectura que falla si alguien lo hace.

## Qué falta

La capa de Presentación (controladores, rutas, consumidores de RabbitMQ y comandos de Artisan) y los contratos a acordar con los otros microservicios. El detalle, con su justificación, está en la sección 10 de [`CASOS_DE_USO.md`](CASOS_DE_USO.md).
