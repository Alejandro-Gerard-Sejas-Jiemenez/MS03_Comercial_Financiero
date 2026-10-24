# MS-03 Comercial y Financiero — Backend (Clean Architecture, Laravel)

Objetivo: implementar el backend de MS-03 (Dominio, Aplicación, Infraestructura) siguiendo `context/MS-03 Comercial y Financiero — Arquitectura Clean (Laravel).md`, sin capa de Presentación, y documentar los 35 casos de uso en la raíz del proyecto.
Rama: (sin repositorio git inicializado al inicio del trabajo) · Estrategia de entrega: `single-pr` (pendiente de repositorio)
Test runner: `php artisan test` (PHPUnit de Laravel); dominio y aplicación sin base de datos.

## Specs

- **S1** — Alcance: solo backend, sin capa de Presentación. Texto del usuario: "quiero que lo que es backend ,aun nada de presentacion".
  Criterio: existen `Domain/`, `Application/` e `Infrastructure/` en cada paquete; **no** se crean `Presentation/Http` (controladores, rutas, resources) ni `Presentation/Messaging`. Los puertos que la Presentación usaría quedan definidos y enlazados.
- **S2** — La arquitectura de referencia es el documento Clean. Texto del usuario: "puedes darle mas prioridad a @\"context\\MS-03 Comercial y Financiero — Arquitectura Clean (Laravel).md\" que es la arquitetura que busco".
  Criterio: estructura `src/<Paquete>/{Domain,Application,Infrastructure}`, regla de dependencias `Application → Domain ← Infrastructure`, dominio sin `Illuminate\*`, convenciones de nombres del documento (`VerboSustantivoCommand` + `Handler`, `PagoRepository` en Domain, `EloquentPagoRepository`, `PagoModel`).
- **S3** — Documentar los casos de uso en la raíz. Texto del usuario: "docuemntar los casos de uso en la raiz del proyecto".
  Criterio: archivo en la raíz del proyecto con los 35 casos de uso (ID, nombre, tipo, disparador, qué hace, entradas/salidas, reglas, eventos, estado de implementación).
- **S4** — Insumos: todo el directorio `context/`. Texto del usuario: "en base al direcotrio de @context\\".
  Criterio: el modelo de datos viene de `ms03_comercial_financiero.sql` (19 tablas, esquema `comercial`) y las integraciones de `ms03_modelo_y_conexiones.md`.
- **S5** (derivado de S2) — 7 paquetes: `Shared`, `Referencias`, `Monetizacion`, `Acuerdos`, `Promociones`, `Pagos`, `Liquidaciones`.
- **S6** (derivado de S2) — 35 casos de uso con los IDs del documento: CU-MON-01..02, CU-ACU-01..04, CU-PRO-01..04, CU-PAG-01..12, CU-LIQ-01..10, CU-REF-01..02, CU-SHA-01.
- **S7** (derivado de S2) — Clean completa (entidades puras + repositorios + mapeo) en `Pagos` y `Liquidaciones`; CRUD agrupado en servicios `Gestionar…` con Eloquent detrás del servicio en los demás paquetes (decisión pendiente #5 del documento: "Entidades puras en `Pagos` y `Liquidaciones`; Eloquent directo detrás del servicio en el CRUD").
- **S8** (derivado de S4) — Migraciones equivalentes al DDL: esquema `comercial`, `CHECK (monto_total = subtotal_alojamiento - descuento + cargo_servicio)`, índices únicos parciales (`uq_pago_vivo_por_reserva`, `uq_acuerdo_activo_por_anfitrion`, `uq_metodo_principal`), triggers append-only en `transaccion_pago` y `movimiento_contable`, `idempotency_key` única.
- **S9** (derivado de S2) — `Shared`: `Money`, Outbox (`outbox_evento`) dentro de la transacción del caso de uso, Inbox (`evento_procesado`), gestor de transacciones. Fórmulas: `total = subtotal − descuento + cargo_servicio`; `monto_comision = base_comisionable × %`; `neto_anfitrion = base − comisión`; `total_neto = bruto − comisiones − reembolsos`.
- **S10** (derivado de S2) — Pruebas: `tests/Unit` (dominio puro, sin Laravel ni BD) y `tests/Application` (casos de uso con dobles en memoria). `tests/Integration` y `tests/Contract` quedan con su estructura; su ejecución depende de PostgreSQL y de los otros microservicios.
- **Supuesto A** — El proyecto Laravel se instala en la raíz del directorio de trabajo (junto a `context/`), de modo que "la raíz del proyecto" de S3 es ese mismo directorio.
- **Supuesto B** — Sin repositorio git: los commits de unidad de trabajo quedan pendientes de que el usuario autorice `git init`.

## Tasks

| ID | S# | Ruta | Estado | Evidencia |
| --- | --- | --- | --- | --- |
| T1 | S1,S2,S4 | inline | hecho | Laravel 12.70.1 en la raíz. PSR-4 `Src\` → `src/`, `Database\Support\` → `database/support/`; `phpunit.xml` con suites Unit/Application/Architecture/Integration; `config/comercial.php`; `.env` a PostgreSQL. `php artisan about` responde |
| T2 | S8 | inline | hecho | 10 migraciones = 19 tablas. `docker compose up -d` + `php artisan migrate` contra PostgreSQL 16: 11 pasos OK; `information_schema` confirma **19 tablas** en `comercial`. Defecto encontrado en el DDL de `context/`: `conciliacion.estado VARCHAR(12)` no alcanza para `'CON_DIFERENCIAS'` (15 car.) → corregido a 20 |
| T3 | S9 | inline | hecho | `src/Shared`: `Money` (16 pruebas), `DomainEvent`, `RegistraEventos`, puertos `EventPublisher`/`TransactionManager`/`Clock`/`UuidGenerator`/`InboxRegistry`/`MessageBroker`/`AuditLogger`, adaptadores Eloquent, CU-SHA-01 |
| T4 | S6,S7 | inline | hecho | `src/Referencias`: CU-REF-01, CU-REF-02, `AnfitrionReference`/`ReservaReference` + repositorios Eloquent. Ejercitado por las pruebas de CU-LIQ-03/04 |
| T5 | S6,S7,S9 | inline | hecho | `src/Monetizacion`: `ReglaMonetizacion`, `CalculadoraComision`, `DesgloseComercial`, CU-MON-01, CU-MON-02. 9 pruebas de la calculadora en verde |
| T6 | S6,S7 | delegado | hecho | `src/Acuerdos`: CU-ACU-01..04, puertos `AcuerdoComercialQuery`/`MetodoCobroQuery`/`CifradorDeDatos`. 59 pruebas propias del subagente en verde |
| T7 | S6,S7 | delegado | hecho | `src/Promociones`: CU-PRO-01..04, puertos `SolicitudDescuento`/`DescuentoAplicado`/`CalculadorDeDescuento`. 46 pruebas propias del subagente en verde |
| T8 | S6,S7,S9 | inline | hecho | `src/Pagos`: CU-PAG-01..12 completos, entidades `Pago`/`Reembolso`/`Comprobante`/`Conciliacion`, `PoliticaDeReembolso`, 6 modelos y 5 repositorios Eloquent, `PasarelaPagoSimulada`, `NumeradorComprobantesSecuencial`. 18 pruebas de `Pago` + 10 de cobro en verde |
| T9 | S6,S7,S9 | inline | hecho | `src/Liquidaciones`: CU-LIQ-01..10, `Comision`, `Liquidacion`, `Asiento` (partida doble), 3 modelos y 4 repositorios Eloquent incluido `EloquentConsultaDeReportes`. 17 + 9 pruebas en verde |
| T10 | S10 | inline | hecho | `php vendor/bin/phpunit`: **233 pruebas, 584 aserciones, 0 fallos** (248 con las 15 de integración). 15 pruebas de integración contra PostgreSQL real en verde |
| T11 | S3 | inline | hecho | `CASOS_DE_USO.md` en la raíz: los 35 casos de uso con contrato, reglas, eventos, archivo y pendientes. `README.md` reescrito para el proyecto |
| T12 | S2 | inline | hecho | `tests/Architecture/ReglaDeDependenciasTest.php` (6) + `ContenedorResuelveLosEnlacesTest.php` (35). Check verificado: al importar `Illuminate\Support\Str` en `src/Pagos/Domain/Pago.php` falla nombrando archivo e import |

Alcance de cada tarea:

- **T1** Proyecto Laravel en la raíz: PSR-4 `Src\` → `src/`, `ComercialServiceProvider` registrado, `config/comercial.php`, `.env.example` con PostgreSQL.
- **T2** Migraciones de las 19 tablas del DDL, con sus `CHECK`, índices únicos parciales y triggers append-only.
- **T3** `src/Shared`: `Money`, `DomainEvent`, puerto `EventPublisher` + Outbox, `TransactionManager`, Inbox, `Clock`, CU-SHA-01.
- **T4** `src/Referencias`: CU-REF-01, CU-REF-02 y los puertos de lectura que consumen Pagos y Liquidaciones.
- **T5** `src/Monetizacion`: `ReglaMonetizacion`, `CalculadoraComision`, CU-MON-01, CU-MON-02.
- **T6** `src/Acuerdos`: CU-ACU-01..04 y el puerto que expone `dias_liquidacion`.
- **T7** `src/Promociones`: CU-PRO-01..04 y el cálculo de descuento.
- **T8** `src/Pagos`: entidades `Pago`, `Reembolso`, `Comprobante`, `Conciliacion`; puerto `PasarelaPago`; CU-PAG-01..12.
- **T9** `src/Liquidaciones`: `Comision`, `Liquidacion`, libro contable; CU-LIQ-01..10.
- **T10** `tests/Unit` y `tests/Application` con dobles en memoria.
- **T11** `CASOS_DE_USO.md` en la raíz del proyecto.
- **T12** Prueba que falla si se rompe la regla de dependencias (dominio sin `Illuminate\*`).

## Log

**L1 — Solicitud original del usuario (verbatim, 2026-10-23):**

> en base al direcotrio de @context\ quiero que lo que es backend ,aun nada de presentacion ,puedes darle mas prioridad a @"context\MS-03 Comercial y Financiero — Arquitectura Clean (Laravel).md" que es la arquitetura que busco y docuemntar los casos de uso en la raiz del proyecto

**L2 — Exploración (2026-10-23).** `context/` tiene 8 archivos; los que mandan son el documento de arquitectura Clean (prioridad explícita del usuario), `ms03_comercial_financiero.sql` (19 tablas, triggers append-only, índices únicos parciales) y `ms03_modelo_y_conexiones.md` (eventos y REST con MS-01, MS-02, MS-04). Entorno: PHP 8.2.35 con `pdo_pgsql`, Composer 2.8.8, git 2.45, Docker 28. El directorio de trabajo no es un repositorio git.

**L3 — Decisión de alcance.** "aun nada de presentacion" (S1) se interpreta como: no se crean controladores, rutas HTTP, Form Requests, Resources ni consumidores RabbitMQ. Sí se crean los puertos/interfaces que esa capa usaría y el enlace en el `ServiceProvider`, de modo que agregar Presentación después sea solo escribir adaptadores. Los disparadores REST/EVT quedan documentados en `CASOS_DE_USO.md` como contrato pendiente.

**L5 — Corte y reanudación por límite de uso (2026-10-24).** La sesión se cortó por cuota con T6/T7 delegados a medias y T8/T9 pendientes. Al restablecerse se reanudaron los dos subagentes con `SendMessage` (conservando su contexto, en vez de relanzarlos) y el padre siguió con Pagos y Liquidaciones. Los dos subagentes terminaron sus paquetes en verde.

**L10 — Cierre verificado (2026-10-24).** `php vendor/bin/phpunit`: 233 pruebas, 584 aserciones, 0 fallos (248 contando las 15 de integración que se omiten sin PostgreSQL). `--testsuite=Integration` contra PostgreSQL 16 en Docker: 15 pruebas, 22 aserciones, en verde. `php -l` sin errores en los 220 archivos de `src/`. Los 35 casos de uso están implementados: Pagos 12, Liquidaciones 10, Acuerdos 4, Promociones 4, Monetizacion 2, Referencias 2, Shared 1.

**L11 — Defecto encontrado en el insumo.** `context/ms03_comercial_financiero.sql` declara `conciliacion.estado VARCHAR(12)` y su propio `CHECK` admite `'CON_DIFERENCIAS'` (15 caracteres): toda conciliación con diferencias fallaba con `value too long for type character varying(12)`. Lo detectó una prueba de integración, no una lectura del esquema. La migración lo corrige a `VARCHAR(20)` con el comentario explicando por qué difiere del DDL original.

**L12 — Pendientes declarados al cierre.** (1) Capa de Presentación completa, por decisión del usuario (S1): controladores, rutas, middleware de Keycloak, consumidores de RabbitMQ y comandos de Artisan para los disparadores JOB. (2) Suite `Contract`: carpeta creada, sin casos, porque depende de acordar los contratos con MS-01, MS-02 y MS-04. (3) Siete decisiones pendientes anotadas en el código y listadas en la sección 10 de `CASOS_DE_USO.md`. (4) `git init` y los commits de unidad de trabajo: el directorio no es un repositorio y eso requiere autorización del usuario (Supuesto B).

**Risk: item 6** (no había pruebas que atraparan una regresión, porque el proyecto nació vacío) — mitigado: 248 pruebas, incluidas 6 de arquitectura y 15 contra PostgreSQL real. Todo el código es nuevo; no se modificó ningún comportamiento existente. Items 1 a 5: no aplican (sin datos previos, sin contratos ya consumidos por terceros, sin concurrencia nueva, sin cambios de despliegue).

**L6 — Decisión: `Money` en centavos.** `Money` guarda enteros (centavos) y la base usa `NUMERIC(12,2)`; el mapeo convierte con `deDecimal()`/`aDecimal()` en los repositorios. Evita el error de punto flotante en las reglas de dinero, que es justamente lo que el documento manda proteger en PHP puro. Verificado: se rompió a propósito el redondeo half-up y la prueba `test_el_porcentaje_redondea_el_medio_centavo_hacia_arriba` falló (`123 is not identical to 124`); al restaurar, verde.

**L7 — Supuesto nuevo: política de reembolso.** El documento del proyecto no define los tramos de cancelación. `src/Pagos/Domain/PoliticaDeReembolso.php` propone 100 % con 7+ días, 50 % entre 3 y 6, 0 % con menos de 3, y 100 % si cancela el anfitrión. Está marcado como SUPUESTO en el código y hay que confirmarlo con el negocio.

**L4 — Decisión de profundidad por paquete.** Se sigue la decisión pendiente #5 del documento de arquitectura: entidades de dominio puras con repositorios y mapeo en `Pagos` y `Liquidaciones`; en `Monetizacion`, `Acuerdos`, `Promociones` y `Referencias` el CRUD se agrupa en servicios `Gestionar…` que usan Eloquent detrás de una interfaz de Aplicación. Evita el costo de sobre-ingeniería que el propio documento señala.
