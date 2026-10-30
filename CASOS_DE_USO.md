# MS-03 Comercial y Financiero — Casos de uso

Microservicio de **Aloja-Te** (INF-512, Grupo 6) en **Clean Architecture pura**: 5 módulos, **15 casos de uso** y **44 endpoints** que usan solo `GET`, `POST` y `PUT`.

El detalle fino de cada operación (fórmulas de dinero, invariantes, eventos y pendientes) está en [`docs/CASOS_DE_USO_DETALLE.md`](docs/CASOS_DE_USO_DETALLE.md), escrito cuando eran 35 casos de uso. Hoy esos 35 son las *operaciones* de estos 15.

## 1. Cómo se organiza

Cada módulo tiene los cuatro círculos de Clean Architecture; las dependencias solo apuntan hacia adentro:

```text
Domain  <-  Application  <-  Adapters  <-  Infrastructure
entidades   casos de uso      HTTP, consola,   Eloquent, RabbitMQ,
y reglas    + puertos         mensajeria,      Keycloak, cifrado
            (Request/Response) presentadores
```

| Círculo | Contiene | Ejemplo |
| --- | --- | --- |
| **Domain** | Entidades, objetos de valor, eventos de dominio, excepciones | `Pago`, `Money`, `PagoCapturado` |
| **Application** | Interfaz de entrada, servicio, modelos `Request` y `Response`, puertos de salida | `ProcesarPagoUseCase`, `CrearPagoRequest`, `PagoResponse`, `PagoRepository` |
| **Adapters** | Controladores, consola, suscriptores de eventos, presentadores | `PagoController`, `FinancieroSuscriptor`, `FinancieroPresentador` |
| **Infrastructure** | Frameworks y drivers: repositorios Eloquent, broker, JWT, cifrado | `EloquentPagoRepository`, `RabbitMqMessageBroker` |

Los casos de uso **no devuelven entidades**: devuelven un `Response`, y el presentador del módulo lo convierte en el JSON. El verificador `php artisan arquitectura:verificar` falla si alguna capa importa hacia afuera.

## 2. Los 15 casos de uso

| # | Módulo | Caso de uso | Operaciones |
| --- | --- | --- | --- |
| 01 | Financiero | `ProcesarPago` | cotizar, crear, cobrar, cancelarPendiente, recibirNotificacionPasarela, emitirComprobante |
| 02 | Financiero | `ConsultarPagos` | listar, detalle (incluye transacciones, reembolsos y comprobante) |
| 03 | Financiero | `GestionarReembolsos` | solicitar, revisar, procesar |
| 04 | Financiero | `ConciliarTransacciones` | ejecutar, revisarDiferencia |
| 05 | Financiero | `GestionarComisiones` | registrar, revertir |
| 06 | Financiero | `GestionarLiquidaciones` | marcarLiquidables, generar, aprobar, rechazar, pagar, retener |
| 07 | Financiero | `ConsultarSaldosYReportes` | todas, historialDe, detalle, saldoDe, porLiquidarDe, ingresos, porCategoria |
| 08 | Comercial | `GestionarPromociones` | crear, actualizar (datos, alojamientos, estado), listar, consultar, vencerPorFecha |
| 09 | Comercial | `AplicarPromocion` | calcularDescuento, registrarUso |
| 10 | Monetizacion | `GestionarReglasMonetizacion` | crear, actualizar (incluye desactivar), listar, vigentes, consultar |
| 11 | Monetizacion | `CalcularDesgloseComercial` | calcular (también lo usa Financiero por el puerto `CalculadorDeDesglose`) |
| 12 | Relaciones | `GestionarAcuerdosComerciales` | crear, actualizarCondiciones, cambiarEstado, listar, consultar, finalizarVencidos |
| 13 | Relaciones | `GestionarMetodosCobro` | registrar, actualizar (incluye principal y baja), listar, consultar, verificar |
| 14 | Shared | `SincronizarReferencias` | sincronizarAnfitrion, sincronizarReserva |
| 15 | Shared | `PublicarEventos` | publicarPendientes (relay del Outbox) |

## 3. Endpoints (44)

Prefijo `/api/v1`. Todos exigen JWT de Keycloak (roles `ADMIN`, `ANFITRION`, `CLIENTE`) salvo `salud` y el webhook de la pasarela (secreto compartido).

**Regla de `PUT`:** actualiza *lo que se envía*, no reemplaza el recurso. El `estado` (y `activo` en métodos de cobro) viaja **solo**: si se manda junto con otros campos, la API responde `422`. No hay `DELETE`: las bajas son lógicas.

| Caso de uso | Método y ruta | Roles |
| --- | --- | --- |
| 01 ProcesarPago | `POST /pagos/cotizaciones` | CLIENTE, ADMIN |
| | `POST /pagos` (Idempotency-Key) | CLIENTE, ADMIN |
| | `POST /pagos/{id}/cobro` (Idempotency-Key) | CLIENTE, ADMIN |
| | `POST /pagos/{id}/cancelacion` | CLIENTE, ADMIN |
| | `POST /pagos/{id}/comprobante` | CLIENTE, ADMIN |
| | `POST /pagos/notificaciones-pasarela` (webhook) | secreto compartido |
| 02 ConsultarPagos | `GET /pagos`, `GET /pagos/{id}` | CLIENTE, ANFITRION, ADMIN |
| 03 GestionarReembolsos | `POST /reembolsos` | CLIENTE, ADMIN |
| | `POST /reembolsos/{id}/revision`, `POST /reembolsos/{id}/procesamiento` | ADMIN |
| 04 ConciliarTransacciones | `POST /conciliaciones`, `POST /conciliaciones/{id}/revisiones` | ADMIN |
| 06 GestionarLiquidaciones | `POST /liquidaciones/{id}/aprobacion`, `/rechazo`, `/pago` | ADMIN |
| 07 ConsultarSaldosYReportes | `GET /liquidaciones` | ADMIN |
| | `GET /liquidaciones/{id}` | ANFITRION, ADMIN |
| | `GET /anfitriones/{id}/liquidaciones`, `/saldo`, `/comisiones-por-liquidar` | ANFITRION, ADMIN |
| | `GET /reportes/ingresos`, `GET /reportes/ingresos-por-categoria` | ADMIN |
| 08 GestionarPromociones | `GET /promociones`, `POST /promociones`, `GET /promociones/{id}` | ANFITRION, ADMIN |
| | `PUT /promociones/{id}` (datos, `alojamientos` o `estado`) | ANFITRION (dueño), ADMIN |
| 09 AplicarPromocion | `POST /promociones/descuento` | CLIENTE, ANFITRION, ADMIN |
| 10 GestionarReglasMonetizacion | `GET /reglas-monetizacion`, `GET /reglas-monetizacion/vigentes`, `POST /reglas-monetizacion`, `GET /reglas-monetizacion/{id}` | ADMIN |
| | `PUT /reglas-monetizacion/{id}` (datos o `estado: INACTIVA`) | ADMIN |
| 11 CalcularDesgloseComercial | `POST /desglose-comercial` | CLIENTE, ANFITRION, ADMIN |
| 12 GestionarAcuerdosComerciales | `GET /acuerdos`, `GET /acuerdos/{id}` | ANFITRION (suyos), ADMIN |
| | `POST /acuerdos`, `PUT /acuerdos/{id}` (condiciones o `estado`) | ADMIN |
| 13 GestionarMetodosCobro | `GET/POST /anfitriones/{id}/metodos-cobro`, `GET /metodos-cobro/{id}` | ANFITRION (suyos), ADMIN |
| | `PUT /metodos-cobro/{id}` (datos, `es_principal` o `activo: false`) | ANFITRION (dueño), ADMIN |
| | `POST /metodos-cobro/{id}/verificacion` | ADMIN |
| — | `GET /salud` | público |

## 4. Disparadores automáticos

**Eventos que consume** (RabbitMQ, con Inbox idempotente; cada módulo tiene un suscriptor):

| Evento | Suscriptor | Efecto |
| --- | --- | --- |
| `anfitrion.verificado`, `anfitrion.suspendido`, `reserva.confirmada`, `reserva.actualizada`, `reserva.cancelada` | `ReferenciasSuscriptor` (Shared) | Copia local del anfitrión y de la reserva |
| `reserva.cancelada` | `FinancieroSuscriptor` | Abre la solicitud de reembolso |
| `pago.capturado` | `FinancieroSuscriptor` | Registra la comisión |
| `pago.capturado` | `ComercialSuscriptor` | Cuenta un uso de la promoción |
| `reembolso.procesado` | `FinancieroSuscriptor` | Revierte la comisión |
| `estadia.completada` | `FinancieroSuscriptor` | Marca las comisiones como liquidables |
| `anfitrion.suspendido` | `FinancieroSuscriptor` | Retiene las liquidaciones del anfitrión |

**Eventos que publica** (Outbox transaccional): `pago.capturado`, `pago.fallido`, `pago.cancelado`, `reembolso.procesado`, `liquidacion.pagada`, `promocion.aplicada` y los eventos de cambio de estado de los acuerdos.

**Jobs** (Scheduler, `routes/console.php`):

| Comando | Cuándo | Caso de uso |
| --- | --- | --- |
| `outbox:publicar` | cada minuto | 15 PublicarEventos |
| `pagos:expirar-pendientes` | cada hora | 01 ProcesarPago (cancelarPendiente) |
| `promociones:vencer` | 00:05 | 08 GestionarPromociones (vencerPorFecha) |
| `acuerdos:finalizar-vencidos` | 00:10 | 12 GestionarAcuerdosComerciales (finalizarVencidos) |
| `conciliacion:ejecutar` | 02:00 | 04 ConciliarTransacciones |
| `liquidaciones:generar` | 03:00 | 06 GestionarLiquidaciones |

`mensajeria:consumir` corre aparte (proceso largo que lee las colas) y vive en `Infrastructure` porque depende de RabbitMQ.

## 5. Pendientes que no se deciden solos

Siguen abiertos los puntos de la sección 10 de [`docs/CASOS_DE_USO_DETALLE.md`](docs/CASOS_DE_USO_DETALLE.md); entre ellos los nombres definitivos de eventos y roles con MS-01 y MS-02, y la política de reembolso (100 % con 7 días o más, 50 % de 3 a 6, 0 % con menos de 3, 100 % si cancela el anfitrión), que es un **supuesto**.
