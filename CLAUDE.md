# MS-03 Comercial y Financiero

Laravel 12 / PHP 8.2 / PostgreSQL 16 (docker-compose, puerto 5433). **Clean Architecture pura** en `src/<Modulo>/{Domain,Application,Adapters,Infrastructure}`, namespace `Src\`. Casos de uso: `CASOS_DE_USO.md`. Seguimiento: `odd/tasks/`.

## Estado

- 5 módulos: `Financiero`, `Comercial`, `Monetizacion`, `Relaciones`, `Shared`. 15 casos de uso, 44 endpoints (solo GET/POST/PUT), 20 tablas en el esquema `comercial`, 12 migraciones.
- `app/Providers/ComercialServiceProvider.php` es la raíz de composición: enlaza puertos con implementaciones y las 15 interfaces de caso de uso con su servicio. Broker = `LogMessageBroker` por defecto, `RabbitMqMessageBroker` con `COMERCIAL_BROKER_DRIVER=rabbitmq`; pasarela = `PasarelaPagoSimulada`.
- Frontend (`frontend/`): shell single-spa + portal React + admin Angular. Realm de Keycloak en `docker/keycloak/`.
- No hay suite PHPUnit: se eliminó `tests/` (era de la arquitectura anterior). La red de seguridad es `scripts/humo.php` (extremo a extremo contra Docker), `scripts/dominio_promocion.php` (reglas de la entidad `Promocion`, con `--guardar` y `--comparar`), `php artisan arquitectura:verificar` y `vendor/bin/pint --test`. Sin `config/cache.php`, `session.php` ni `queue.php` hay que fijar `CACHE_STORE=file`, `SESSION_DRIVER=array` y `QUEUE_CONNECTION=sync` por entorno (ya van en `docker-compose.yml` y `scripts/servir-local.sh`).
- Sin verificar: el workflow de GitHub Actions. Faltan: esquemas de contrato para el resto de eventos, servidor de producción (hoy `artisan serve`), reproceso de `comercial.muertos`.

## Reglas que no se rompen

1. **Dependencias solo hacia adentro:** `Domain <- Application <- Adapters <- Infrastructure`. Lo verifica `php artisan arquitectura:verificar`. Domain y Application no importan `Illuminate\*`.
2. Los casos de uso reciben un `Request` y devuelven un `Response`, **nunca una entidad**. El presentador del módulo (`Adapters/Presenter`) arma el JSON.
3. Controlador delgado: validar request -> armar `Request` -> caso de uso -> presentador. Cero lógica de negocio.
4. Las interfaces de repositorio son puertos en `Application/Port`; su implementación Eloquent va en `Infrastructure/Persistence`.
5. Dinero siempre en centavos (`Money`). Sin FKs entre microservicios (UUID suaves).
6. API: solo `GET`, `POST` y `PUT`. `PUT` actualiza lo que se envía; el `estado` viaja solo. Sin `DELETE`: las bajas son lógicas.
7. Toda ruta nueva entra al recorrido de `scripts/humo.php`.
8. Antes de dar un cambio por bueno: `php artisan arquitectura:verificar`, `scripts/humo.php` y `vendor/bin/pint --test`.

## Decisiones tomadas

| Tema | Decisión |
|---|---|
| Arquitectura | Clean Architecture (única; se descartó DDD por exigencia de la materia) |
| Saga | MS-02 orquesta; MS-03 participante: `crear`/`cobrar`, compensaciones `cancelarPendiente`/reembolso |
| Eventos | Outbox transaccional + Inbox idempotente, RabbitMQ |
| Idempotencia HTTP | Header `Idempotency-Key` en POST de pago/reembolso; tabla `idempotencia_http` con hash del body |
| Excepciones | Tipadas en Domain (subclases de `DomainException`), mapeadas a HTTP en Adapters |
| Auth | Keycloak (JWT estándar) detrás del puerto `AutenticadorDeSolicitud` |
| Migraciones | En `database/`, fuera de los cuatro círculos |

## Autenticación

- Puerto `Src\Shared\Application\Auth\AutenticadorDeSolicitud` -> devuelve `Sesion(usuarioId, roles[])` o lanza 401.
- Implementación `Src\Shared\Infrastructure\Auth\KeycloakJwtAutenticador`: verifica firma con JWKS de Keycloak (cache), `exp`, `iss`, `aud`. `sub` = `anfitrion_id` / `cliente_id`. Roles: `ADMIN`, `ANFITRION`, `CLIENTE`.
- Config en `config/comercial.php` <- env `KEYCLOAK_*`. Sin secretos compartidos. Middleware `sesion` y `rol`.
- MS-01 hoy emite un token HMAC propio; migrar a Keycloak hay que acordarlo con el equipo de MS-01 o agregar otra implementación del puerto.

## Pendientes que NO se deciden solos

- Los puntos abiertos de la sección 10 de `docs/CASOS_DE_USO_DETALLE.md`.
- Nombres de eventos y roles/permisos definitivos (acordar con MS-01 y MS-02).
- Política de reembolso (100 % >=7 días, 50 % 3-6, 0 % <3, 100 % si cancela el anfitrión): SUPUESTO.
- Historial/bitácora de descuentos: descartado por ahora.
