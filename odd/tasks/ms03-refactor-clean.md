# MS-03 · Refactorizacion a Clean Architecture pura

Objetivo: 15 casos de uso, 5 modulos (Financiero, Comercial, Monetizacion, Relaciones, Shared), 44 rutas con GET/POST/PUT (43 + `GET /liquidaciones`), y las 4 capas de Clean con sus nombres del libro. Sin cambiar las reglas de negocio.
Rama: `refactor/clean-pura` · Entrega: un commit por fase (se revierte por fase) · Verificacion: ver "Red de seguridad".

## Specs

- **S1** — Arquitectura **netamente pura**: Clean Architecture. Capas por modulo: `Domain` (entidades) -> `Application` (casos de uso y puertos) -> `Adapters` (http, console, messaging, persistencia) -> `Infrastructure` (frameworks y drivers). Las dependencias solo apuntan hacia adentro.
- **S2** — 7 paquetes pasan a 5 modulos: Pagos+Liquidaciones = `Financiero`; Promociones = `Comercial`; Acuerdos = `Relaciones`; Monetizacion igual; Referencias va a `Shared`.
- **S3** — 35 handlers pasan a 15 casos de uso (servicio con varias operaciones). Cada uno con interfaz de entrada, modelos `Request` y `Response`. Los casos de uso **no devuelven entidades**.
- **S4** — Las 15 interfaces `*Repository` pasan de `Domain` a `Application/Port`.
- **S5** — API de 54 a 43 rutas (44 con `GET /liquidaciones`), solo GET, POST y PUT. Las rutas y sus reglas estan en la lista de la conversacion; los PATCH pasan a PUT, el `estado` viaja solo.
- **S6** — Migraciones y seeders se quedan en `database/` (circulo externo, no dependen de `src/`).
- **S7** — Los frontends no pertenecen a las capas: son clientes del contrato `openapi.yaml`.
- **S8** — Fuera de alcance por ahora: historial/bitacora de descuentos (descartado por el usuario).

## Casos de uso (15)

| # | Modulo | Caso de uso | Operaciones |
|---|---|---|---|
| 01 | Financiero | ProcesarPago | cotizar, crear, cobrar, cancelarPendiente, recibirNotificacionPasarela, emitirComprobante |
| 02 | Financiero | ConsultarPagos | listar, detalle |
| 03 | Financiero | GestionarReembolsos | solicitar, revisar, procesar |
| 04 | Financiero | ConciliarTransacciones | ejecutar, revisarDiferencia |
| 05 | Financiero | GestionarComisiones | registrar, revertir |
| 06 | Financiero | GestionarLiquidaciones | marcarLiquidables, generar, aprobar, rechazar, pagar, retener |
| 07 | Financiero | ConsultarSaldosYReportes | historial, saldo, comisionesPorLiquidar, detalle, todas, ingresos, ingresosPorCategoria |
| 08 | Comercial | GestionarPromociones | crear, actualizar (datos, alojamientos, estado), listar, consultar, vencerPorFecha |
| 09 | Comercial | AplicarPromocion | calcularDescuento, registrarUso |
| 10 | Monetizacion | GestionarReglasMonetizacion | crear, actualizar (incluye desactivar), listar, vigentes, consultar |
| 11 | Monetizacion | CalcularDesgloseComercial | calcular (interno) |
| 12 | Relaciones | GestionarAcuerdosComerciales | crear, actualizarCondiciones, cambiarEstado, listar, consultar, finalizarVencidos (nueva) |
| 13 | Relaciones | GestionarMetodosCobro | registrar, actualizar (incluye principal y baja), listar, consultar, verificar |
| 14 | Shared | SincronizarReferencias | sincronizarAnfitrion, sincronizarReserva |
| 15 | Shared | PublicarEventos | publicarPendientes |

## Red de seguridad (el usuario borrara `tests/`)

Sin PHPUnit no hay red automatica. Para que cada fase se pueda verificar:
1. **Foto inicial:** guardar `route:list` (54 rutas), `openapi.yaml` y respuestas JSON de referencia antes de empezar.
2. **Script de humo versionado** (`scripts/humo.php`): el flujo extremo a extremo contra Docker (login, cotizar, pagar, reembolso, reportes) que ya se uso a mano.
3. **Verificador de arquitectura como comando Artisan** (`php artisan arquitectura:verificar`): reemplaza la prueba de dependencias, para conservar la evidencia de Clean sin PHPUnit.
4. En cada fase: `php -l` de todo `src/`, `route:list`, `openapi:generar --check`, el contenedor resuelve los enlaces, y el script de humo.

## Tasks

| ID | Fase | Tamano | Contenido | Estado |
| --- | --- | --- | --- | --- |
| T0 | Preparacion | S | Rama, fotos iniciales, script de humo, verificador de arquitectura | hecho |
| T1 | Limpieza de plantilla (grupo A) | S | Quitar `routes/web.php` (+ `web:` en `bootstrap/app.php`), `resources/`, `package.json` y `vite.config.js` de la raiz (+ scripts de composer), `User`, `Controller`, `AppServiceProvider` (+ `bootstrap/providers.php`), `database/factories` y `seeders`, `context.zip`; `.atl/` a `.gitignore` | hecho |
| T2 | Reorganizacion fisica | L | 7 paquetes a 5 modulos y 4 capas con `git mv`; `Presentation` pasa a `Adapters`; repositorios concretos a `Adapters/Persistence`, modelos a `Infrastructure`; actualizar provider, rutas, etiquetas del OpenAPI | hecho |
| T3 | Repositorios a puertos | S | Mover las 15 interfaces de `Domain` a `Application/Port` | hecho |
| T4 | Consolidar casos de uso | L | 35 handlers a 15 servicios, con interfaz, `Request` y `Response`; un modulo a la vez: Monetizacion, Relaciones, Comercial, Shared, Financiero | hecho |
| T5 | Respuestas y presentadores | L | `Response` por caso de uso y presentador por modulo; eliminar el `Presentador` reflexivo; el JSON no cambia | hecho |
| T6 | Endpoints (54 a 43) | M | Quitar 11 rutas, 4 PATCH a PUT, estados dentro del PUT, baja de metodos de cobro (migracion 012), job `acuerdos:finalizar-vencidos`, `GET /liquidaciones` opcional; actualizar frontends | hecho |
| T7 | Eventos y jobs | S | `FinancieroSuscriptor` y `ComercialSuscriptor`; revisar el Scheduler | hecho |
| T8 | Documentacion y arquitectura | M | `CASOS_DE_USO.md` (15), `README`, `CLAUDE.md`, diagrama de los 4 circulos, vocabulario de Clean (quitar alusiones a DDD en ~18 archivos), CI, referencias a `tests/` | hecho |
| T9 | Cierre | S | Humo completo en Docker, frontends probados en el navegador, fusion a `main` | hecho |

## Decisiones abiertas

- Config opcional (grupo B): quitar `auth`, `filesystems`, `mail`, `services`. **No** quitar `cache`, `queue` ni `session` (llevan los valores por omision `file`, `sync`, `array`).
- `PUT` se usa como "actualizar lo que envio" (no reemplazo estricto).
- `GET /liquidaciones` (todas): pedido por el documento de conexiones; opcional.
- `context/`: dejar local o versionar en `docs/`.

## Log

**L1 — 2026-10-30, solicitud del usuario (verbatim):** "bueno descartalo de momento contesctremonos en la aqruitectura que ya teniamos que realizar cambios con los nuevos casos de usos la nuva paquetacion como tambien los nuevos enddpoitns y demas"
**L2 — 2026-10-30:** el docente pide una arquitectura **pura** para el microservicio; el usuario eligio Clean Architecture. Se descarta el hibrido con DDD. Opcion A de limpieza elegida (grupo A). Migraciones fuera de las 4 capas: correcto (circulo externo); se mantienen en `database/`.

**L3 — 2026-10-30, desviaciones del diseno y estado final:**
- Persistencia (repositorios Eloquent y modelos) quedo en `Infrastructure/Persistence`, no en `Adapters`: los repositorios implementan puertos de `Application` y usan el framework, asi que son circulo exterior. El Domain quedo plano por modulo con `Event/`.
- Los casos de uso devuelven `Response` (en `Application/Response`) y cada modulo tiene su presentador en `Adapters/Presenter`. `CalcularDesgloseComercial` tambien expone el puerto `CalculadorDeDesglose` (objeto de dominio) para Financiero.
- Rutas finales: 44 (19 GET, 21 POST, 4 PUT). Baja logica de metodos de cobro con la migracion 012 (`activo`). Job `acuerdos:finalizar-vencidos` en el Scheduler.
- `ConsumirMensajesCommand` se movio a `Shared/Infrastructure/Console`: el verificador `arquitectura:verificar` detecto que un adaptador importaba Infrastructure.
- Suscriptores: `FinancieroSuscriptor` (reembolsos, comisiones, liquidaciones), `ComercialSuscriptor`, `ReferenciasSuscriptor`.
- `tests/` quedo obsoleto (referencia handlers y paquetes anteriores); el usuario lo borrara. Red de seguridad actual: `scripts/humo.php`, `arquitectura:verificar`, `openapi:generar --check`.
