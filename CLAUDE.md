# MS-03 Comercial y Financiero

Laravel 12 / PHP 8.2 / PostgreSQL 16 (docker-compose, puerto 5433). Clean Architecture en `src/<Paquete>/{Domain,Application,Infrastructure}`, namespace `Src\`. Casos de uso: `CASOS_DE_USO.md`. Seguimiento: `odd/tasks/`.

## Estado

- 7 paquetes: `Shared`, `Referencias`, `Monetizacion`, `Acuerdos`, `Promociones`, `Pagos`, `Liquidaciones`. 35 casos de uso, 19 tablas en el esquema `comercial`, 10 migraciones.
- 126 tests (110 + 16 de integracion que requieren PostgreSQL), 635 aserciones; P1, P2 (REST + JOBs), P4 (RabbitMQ: broker, consumidores con Inbox) y P3 (`openapi.yaml`, se regenera con `php artisan openapi:generar`) hechos. Suite reducida a proposito (antes 248): un test por regla relevante.
- `app/Providers/ComercialServiceProvider.php` enlaza puertos con implementaciones. Broker = `LogMessageBroker` por defecto, `RabbitMqMessageBroker` con `COMERCIAL_BROKER_DRIVER=rabbitmq`; pasarela = `PasarelaPagoSimulada`.
- P5 hecho. Verificado con PostgreSQL, RabbitMQ y Keycloak reales en Docker (ver L9 en `odd/tasks/ms03-presentacion.md`). Sin verificar: el workflow de GitHub Actions. Faltan: esquemas de contrato para el resto de eventos, servidor de produccion (hoy `artisan serve`), reproceso de `comercial.muertos`.

## Reglas que no se rompen

1. Dependencias: `Application -> Domain <- Infrastructure`. Presentation solo llama a Application. El dominio no importa `Illuminate\*` (lo verifica `tests/Architecture`).
2. Controlador delgado: validar request -> armar Command -> Handler -> Resource JSON. Cero logica de negocio.
3. No tocar `Domain/` ni `Application/` salvo bug real o lo autorizado en `odd/tasks/`. Excepcion autorizada: excepciones tipadas (ver `ms03-presentacion.md`).
4. Dinero siempre en centavos (`Money`). Sin FKs entre microservicios (UUID suaves).
5. Todo cambio termina con `php vendor/bin/phpunit` en verde, incluida la suite `Architecture`.
6. Cada ruta nueva lleva su test HTTP (feature) y entra al OpenAPI.

## Decisiones tomadas

| Tema | Decision |
|---|---|
| Arquitectura | Clean Architecture (unica) |
| Saga | MS-02 orquesta; MS-03 participante: `CrearPago`/`CobrarPago`, compensaciones `CancelarPagoPendiente`/`ProcesarReembolso` |
| Eventos | Outbox transaccional + Inbox idempotente, RabbitMQ |
| Idempotencia HTTP | Header `Idempotency-Key` en POST de pago/reembolso; tabla nueva (migracion 011) con hash del body |
| Excepciones | Tipadas en Domain (subclases de `DomainException`), mapeadas a HTTP en Presentation |
| Auth | Keycloak (JWT estandar) detras del puerto `AutenticadorDeSolicitud` |

## Autenticacion

- Puerto `Src\Shared\Presentation\Auth\AutenticadorDeSolicitud` -> devuelve `Sesion(usuarioId, roles[])` o lanza 401.
- Impl. `KeycloakJwtAutenticador`: verifica firma con JWKS de Keycloak (cache), `exp`, `iss`, `aud`. `sub` = `anfitrion_id` / `cliente_id`. Roles: `ADMIN`, `ANFITRION`, `CLIENTE`.
- Config en `config/comercial.php` <- env `KEYCLOAK_*` (issuer, jwks url, audience). Sin secretos compartidos.
- Middleware `AutenticarSesion` usa solo la interfaz; `RequierePermiso`/rol autoriza. Los tests usan claves RSA generadas, sin servidor real. Keycloak se agrega al `docker-compose.yml` solo para desarrollo.
- Nota: MS-01 hoy emite un token HMAC propio (`base64url(usuarioId|contexto|permisos).firma`, sin expiracion). Migrar a Keycloak hay que acordarlo con el equipo de MS-01; si no, se agrega otra implementacion del puerto sin tocar el resto.

## Plan P1 -> P5 (en orden)

- **P1** Base de Presentation: auth, `RequierePermiso`, `IdempotenciaMiddleware`, mapeo de excepciones a `{codigo, mensaje}` (400/401/403/404/409/422), prefijo `/api/v1`, `GET /api/v1/salud`, rutas por paquete via `loadRoutesFrom`.
- **P2** REST por paquete (35 CU; un controlador por agregado) y comandos Artisan para disparadores JOB.
- **P3** OpenAPI (`openapi.yaml`).
- **P4** Mensajeria: `RabbitMqMessageBroker`, `outbox:publicar`, consumidores con Inbox (`mensajeria:consumir`).
- **P5** Suite `Contract`, CI (GitHub Actions: pint + phpunit con PG16), Dockerfile y servicio `ms03` en compose.

## Pendientes que NO se deciden solos

- Los 7 puntos abiertos de la seccion 10 de `CASOS_DE_USO.md`.
- Nombres de eventos y roles/permisos definitivos (acordar con MS-01 y MS-02).
- Politica de reembolso (100 % >=7 dias, 50 % 3-6, 0 % <3, 100 % si cancela el anfitrion): SUPUESTO.
