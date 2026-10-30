# MS-03 — Comercial y Financiero · Aloja-Te

Microservicio de pagos, comisiones, liquidaciones, promociones y monetización de la plataforma digital de alojamientos **Aloja-Te** (INF-512, Grupo 6).

**Stack:** PHP 8.2 + Laravel 12 · PostgreSQL 16 · RabbitMQ · Keycloak · **Clean Architecture pura**
**Funcionalidades:** F05 (pagos, comisiones y liquidaciones) · F09 (promociones) · F16 (monetización) · F17 (gestión comercial de anfitriones)

| | |
| --- | --- |
| **Casos de uso** | 15, en [`CASOS_DE_USO.md`](CASOS_DE_USO.md) (el detalle de las 35 operaciones originales está en [`docs/`](docs/CASOS_DE_USO_DETALLE.md)) |
| **API** | 44 endpoints REST con `GET`, `POST` y `PUT`; las rutas están en `src/<Módulo>/Adapters/Http/routes.php`. [`openapi.yaml`](openapi.yaml) es una copia estática de la documentación: ya no se genera ni se sirve en `/docs` |
| **Tablas** | 20 en el esquema `comercial` (12 migraciones) |
| **Frontend** | `frontend/`: shell single-spa + portal React + administración Angular ([`frontend/README.md`](frontend/README.md)) |

## Arquitectura

El código vive en `src/`, dividido en módulos por área funcional (`Financiero`, `Comercial`, `Monetizacion`, `Relaciones`) más un núcleo compartido (`Shared`). **Todos los módulos tienen la misma estructura**: los cuatro círculos de Clean Architecture, y dentro de cada uno las mismas subcarpetas. Las dependencias solo apuntan hacia adentro:

```text
Domain  <-  Application  <-  Adapters  <-  Infrastructure
```

```text
src/<Módulo>/
├── Domain/
│   ├── Entity/            entidades; las grandes se reparten en traits por funcionalidad
│   │   └── <Entidad>/       CicloDeVida, Edicion, Lectura, ... (partes de la misma clase)
│   ├── Enum/              estados, tipos y otros valores cerrados
│   ├── ValueObject/       objetos de valor con sus propias reglas
│   ├── Policy/            reglas de negocio que no pertenecen a una sola entidad
│   └── Event/             eventos de dominio
├── Application/
│   ├── Port/              interfaces (repositorios, reloj, broker, cifrado, ...)
│   ├── UseCase/<Caso>/    interfaz del caso de uso, servicio y sus Request
│   └── Response/          modelos de respuesta (solo datos primitivos)
├── Adapters/
│   ├── Http/              controladores y rutas
│   ├── Presenter/         arma el JSON de cada respuesta
│   ├── Console/           comandos artisan y tareas programadas
│   └── Messaging/         suscriptores de eventos
└── Infrastructure/
    ├── Persistence/
    │   ├── Model/           modelos Eloquent
    │   ├── Repository/      implementaciones de los repositorios
    │   └── Query/           consultas de solo lectura
    └── (Gateway, Security, Messaging, ...)   pasarela, cifrado, RabbitMQ, Keycloak
```

`Shared` sigue el mismo esquema y aporta lo común: `Money`, excepciones tipadas, Outbox/Inbox, autenticación, referencias de MS-01 y MS-02, y las clases base de controlador y presentador.

- Los casos de uso reciben un `Request` y devuelven un `Response`; nunca una entidad. Un presentador por módulo arma el JSON.
- Las interfaces de repositorio son puertos de `Application/Port`; sus implementaciones están en `Infrastructure/Persistence/Repository`.
- Una entidad grande sigue siendo una sola clase: sus métodos se reparten en traits (`Entity/<Entidad>/`) solo para facilitar la lectura. Las fábricas, el constructor y los campos se quedan en el archivo principal.
- `app/Providers/ComercialServiceProvider.php` es la raíz de composición: único lugar donde un puerto se enlaza con su implementación.
- Las migraciones están en `database/`, fuera de los cuatro círculos (son infraestructura de despliegue y no dependen de `src/`).
- Los frontends no pertenecen a las capas: son clientes de la API REST.

Verificar la regla de dependencia:

```bash
php artisan arquitectura:verificar
```

## Cómo correrlo

Todo el stack (API, scheduler, consumidor, PostgreSQL, RabbitMQ, Keycloak):

```bash
docker compose up -d --build
curl localhost:8000/api/v1/salud
```

Solo la API local contra el stack de Docker:

```bash
bash scripts/servir-local.sh                      # http://localhost:8001
HUMO_API=http://localhost:8001/api/v1 php scripts/humo.php   # recorrido extremo a extremo
```

## Cómo entrar: usuarios, roles y adónde llevan

Todo entra por el **shell**: `http://localhost:9000`. Pide login en Keycloak y, según el rol, te lleva al portal o a la administración. Usuarios de desarrollo del realm `alojate`; **la contraseña de todos es `clave123`**:

| Usuario | Rol | Entra a | Qué puede hacer |
| --- | --- | --- | --- |
| `admin1` | `ADMIN` | `/admin` (administración, Angular) | Reglas de monetización, acuerdos, pagos y reembolsos, conciliación, liquidaciones y reportes |
| `anfi1` | `ANFITRION` | `/portal` (portal, React) | Pagos recibidos, promociones, métodos de cobro, liquidaciones y saldo |
| `cli1` | `CLIENTE` | `/portal` | Mis pagos, nuevo pago (cotiza y paga), detalle (cobrar, cancelar, comprobante, pedir reembolso) |
| `ambos1` | `CLIENTE` y `ANFITRION` | `/portal` | Todo lo anterior; en *Pagos* elige si los ve como cliente o como anfitrión |

- Un usuario con `ADMIN` entra a `/admin`; cualquier otro, a `/portal`.
- Si un usuario abre la sección que no le corresponde, ve un aviso en vez de la pantalla (un `ADMIN` en `/portal`, o un cliente o anfitrión en `/admin`).
- Para cambiar de usuario, usa *Salir* en el menú lateral.

Direcciones del entorno local:

| Servicio | Dirección |
| --- | --- |
| Shell (entrada) | `http://localhost:9000` |
| Portal React | `http://localhost:5174` (se carga desde el shell) |
| Administración Angular | `http://localhost:4201` (se carga desde el shell) |
| API MS-03 | `http://localhost:8000/api/v1` |
| Keycloak | `http://localhost:8080` (realm `alojate`) |
| Panel de RabbitMQ | `http://localhost:15672` |

Los tres frontends no están en Docker: se levantan con los comandos de [`frontend/README.md`](frontend/README.md).

## Verificación

| Comando | Qué comprueba |
| --- | --- |
| `php artisan arquitectura:verificar` | Las capas solo dependen hacia adentro |
| `php artisan route:list --path=api/v1` | Las 44 rutas |
| `php scripts/humo.php` | Recorrido extremo a extremo contra la API real (cotizar, pagar, reembolsar, liquidar, reportes) |
| `php scripts/dominio_promocion.php --comparar=foto.json` | Las reglas de la entidad `Promocion` (guarda la foto antes con `--guardar=foto.json`) |
| `php scripts/dominio_financiero.php` | Reglas del dominio financiero |
| `vendor/bin/pint --test` | Estilo |

No hay suite PHPUnit: la red de seguridad son estos comandos y scripts.

## Estructura del repositorio

```text
├── CASOS_DE_USO.md          los 15 casos de uso, endpoints, eventos y jobs
├── app/Providers/           raíz de composición
├── config/comercial.php     pasarela, outbox, RabbitMQ, Keycloak, liquidaciones
├── context/                 insumos del proyecto (arquitectura, DDL, diagrama ER)
├── database/                migraciones y restricciones de PostgreSQL
├── docker/                  realm de Keycloak y configuración de contenedores
├── docs/                    detalle de los casos de uso originales
├── frontend/                shell, portal React y administración Angular
├── odd/tasks/               bitácora de la implementación
├── scripts/                 humo.php, dominio_*.php y servir-local.sh
└── src/                     los módulos (Financiero, Comercial, Monetizacion, Relaciones, Shared)
```

## Estado actual

- Backend completo: 15 casos de uso, 44 endpoints y 20 tablas, con la misma estructura de carpetas en todos los módulos.
- Pasan `arquitectura:verificar` y el recorrido `humo.php` completo (47 pasos) contra Docker. `pint --test` aún marca estilo en `bootstrap/providers.php` y `scripts/humo.php`.
- Los contenedores (`docker-compose.yml`) son seis: `postgres`, `rabbitmq`, `keycloak`, y la misma imagen de la aplicación como `api`, `consumidor` y `scheduler`.
- La aplicación necesita `APP_KEY` en el entorno (se usa para cifrar los métodos de cobro): `APP_KEY=$(php artisan key:generate --show) docker compose up -d --build`, o ponerla una vez en `.env`.

## Qué falta

Esquemas de contrato para el resto de los eventos, un servidor de producción (hoy `artisan serve`), el reproceso de la cola `comercial.muertos` y los puntos abiertos de la sección 10 de [`docs/CASOS_DE_USO_DETALLE.md`](docs/CASOS_DE_USO_DETALLE.md). El workflow de GitHub Actions no se verificó.
