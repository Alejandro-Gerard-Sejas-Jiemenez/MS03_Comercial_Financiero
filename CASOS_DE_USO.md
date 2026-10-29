# MS-03 Comercial y Financiero — Casos de uso

Backend del microservicio MS-03 de **Aloja-Te**, implementado con Laravel 12 + PostgreSQL 16 bajo **Clean Architecture**, siguiendo `context/MS-03 Comercial y Financiero — Arquitectura Clean (Laravel).md`.

Son **35 casos de uso** en **7 paquetes**, con un criterio: *un caso de uso por intención de negocio, no por verbo CRUD*. El CRUD puro va junto en un servicio `Gestionar<Entidad>`; las operaciones con reglas de dinero o cambios de estado con eventos tienen su propio `Command` + `Handler`.

**Alcance de esta entrega: solo backend.** Están las capas de Dominio, Aplicación e Infraestructura. **No hay capa de Presentación**: ningún controlador, ruta HTTP, Form Request, Resource, comando de Artisan ni consumidor de RabbitMQ. Los puertos que esa capa usará ya están definidos y enlazados, así que agregarla es escribir adaptadores delgados. La columna **Disparador** de cada ficha dice qué adaptador falta.

---

## 1. Cómo leer este documento

| Campo | Significado |
| --- | --- |
| **Tipo** | `CRUD` servicio `Gestionar…` · `NEG` regla de negocio · `QRY` consulta · `EVT`/`JOB` proceso automático |
| **Disparador** | `REST` (vía API Gateway) · `EVT` (evento de RabbitMQ) · `JOB` (Scheduler/Artisan) · `INT` (llamado interno o evento de dominio) |
| **Entrada** | El `Command`/`Query` que recibe el caso de uso |
| **Salida** | Lo que devuelve |
| **Reglas** | Invariantes que hace cumplir, y quién las garantiza (dominio o base de datos) |
| **Eventos** | Eventos de dominio que emite, publicados por Outbox dentro de la transacción |

Convención de nombres del documento de arquitectura: casos de uso `VerboSustantivoCommand` + `VerboSustantivoHandler`; interfaces de repositorio en `Domain/` sin prefijo (`PagoRepository`); implementación con el nombre de la tecnología (`EloquentPagoRepository`); modelos Eloquent terminados en `Model` (`PagoModel`) para no confundirlos con la entidad `Pago`.

### Las fórmulas de dinero

Están en `src/Monetizacion/Domain/CalculadoraComision.php` y `src/Shared/Domain/Money.php`, en PHP puro, sin base de datos ni Laravel:

```
total (lo que paga el cliente) = subtotal − descuento + cargo_servicio
base_comisionable              = subtotal − descuento financiado por el anfitrión
monto_comision                 = base_comisionable × porcentaje
neto_anfitrion                 = base_comisionable − monto_comision
total_neto (liquidación)       = total_bruto − total_comisiones − total_reembolsos
```

`Money` guarda **centavos enteros** y convierte a `NUMERIC(12,2)` en los repositorios: las reglas de dinero no toleran el error de redondeo del punto flotante. El redondeo del medio centavo es *half-up*.

---

## 2. Resumen por paquete

| Paquete (`src/…`) | Responsabilidad | Func. | Casos de uso | Profundidad |
| --- | --- | --- | --- | --- |
| `Monetizacion` | Reglas de comisión y cargos con vigencia; fórmula de cálculo | F16 | 2 | Entidad pura + repositorio |
| `Acuerdos` | Acuerdos comerciales con anfitriones y su método de cobro | F17 | 4 | Entidad pura + repositorio |
| `Promociones` | Descuentos, cupones, temporadas y estadías prolongadas | F09 | 4 | Entidad pura + repositorio |
| `Pagos` | Cotización, cobro, reembolsos, comprobantes y conciliación | F05 | 12 | Clean completa |
| `Liquidaciones` | Comisiones por reserva, liquidaciones, saldos y reportes | F05 + F16 | 10 | Clean completa |
| `Referencias` | Copias locales de datos de MS-01 y MS-02, alimentadas por eventos | soporte | 2 | Read-model |
| `Shared` | `Money`, Outbox/Inbox, transacciones | transversal | 1 | — |

`Pagos` y `Liquidaciones` llevan todos los anillos (entidades puras, repositorios y mapeo) porque ahí está el dinero. En los demás el CRUD se agrupa en un servicio `Gestionar…`, como decide el documento de arquitectura para no pagar sobre-ingeniería en lo que es sobre todo altas y bajas.

---

## 3. Monetización (F16) — 2 casos de uso

### CU-MON-01 · `GestionarReglasMonetizacion`

| | |
| --- | --- |
| **Tipo / Disparador** | CRUD · REST (Administrador escribe; MS-04 solo lee) |
| **Archivo** | `src/Monetizacion/Application/UseCase/GestionarReglasMonetizacion/GestionarReglasMonetizacionService.php` |
| **Entrada** | `CrearReglaCommand`, `ActualizarReglaCommand`, o `(reglaId, desactivadoPor)` |
| **Salida** | `ReglaMonetizacion`, o `list<ReglaMonetizacion>` en los listados |

Crear, actualizar, desactivar, listar y consultar reglas de comisión (`COMISION_ANFITRION`) y de cargo al cliente (`CARGO_SERVICIO_CLIENTE`), con vigencia y alcance opcional por categoría de alojamiento y nivel de anfitrión.

**Reglas**
- Una regla `PORCENTAJE` exige porcentaje y una `MONTO_FIJO` exige monto: lo valida la entidad y además el `CHECK chk_regla_calculo_coherente`.
- El porcentaje va entre 0 y 100 (`chk_regla_porcentaje`, probado contra PostgreSQL).
- La vigencia no puede terminar antes de empezar.
- **Las reglas no se borran, se desactivan**: el histórico de comisiones ya calculadas guarda su propia tarifa, así que nada cambia hacia atrás.
- Cada escritura queda en `auditoria_log` con el usuario que la hizo.

### CU-MON-02 · `CalcularDesgloseComercial`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · INT (lo llaman CU-PAG-01 y CU-LIQ-01) |
| **Archivo** | `src/Monetizacion/Application/UseCase/CalcularDesgloseComercial/CalcularDesgloseComercialHandler.php` |
| **Entrada** | `CalcularDesgloseComercialCommand(subtotal, descuento, financiamiento, categoría, nivel, reglaNegociadaId?, fecha?)` |
| **Salida** | `DesgloseComercial` (subtotal, descuento, cargo, total, base comisionable, comisión, neto, porcentaje aplicado) |

Resuelve qué reglas aplican y delega la aritmética al servicio de dominio `CalculadoraComision`. Lo usan la cotización y el registro de la comisión, para que **el cliente vea el mismo total que después se cobra y se comisiona**.

**Reglas**
- La regla del acuerdo comercial (tarifa negociada) gana sobre la regla general.
- Entre reglas generales gana la de alcance más específico (categoría + nivel), y a igual especificidad la que empezó a regir más tarde.
- Si el descuento lo financia la **plataforma**, el anfitrión no lo absorbe: la comisión se calcula sobre el subtotal completo. Si lo financia el **anfitrión**, sobre el monto ya rebajado.
- Una regla de cargo al cliente no puede usarse como comisión al anfitrión, ni al revés.
- La comisión nunca supera la base comisionable.

---

## 4. Acuerdos (F17) — 4 casos de uso

### CU-ACU-01 · `GestionarAcuerdos`

| | |
| --- | --- |
| **Tipo / Disparador** | CRUD · REST |
| **Archivo** | `src/Acuerdos/Application/UseCase/GestionarAcuerdos/GestionarAcuerdosService.php` |
| **Entrada** | `CrearAcuerdoBorradorCommand`, `ActualizarCondicionesAcuerdoCommand` |
| **Salida** | `AcuerdoComercial`, o el historial del anfitrión |

Crear el borrador, actualizar condiciones (`condiciones` JSONB y `dias_liquidacion`), listar e historial por anfitrión. Un acuerdo nace en `BORRADOR`.

### CU-ACU-02 · `CambiarEstadoAcuerdo`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · REST / EVT / JOB |
| **Archivo** | `src/Acuerdos/Application/UseCase/CambiarEstadoAcuerdo/CambiarEstadoAcuerdoHandler.php` |
| **Entrada** | `CambiarEstadoAcuerdoCommand(acuerdoId, nuevoEstado, motivo?, aprobadoPor?)` |
| **Salida** | `AcuerdoComercial` |
| **Eventos** | `acuerdo_comercial.activado` · `acuerdo_comercial.suspendido` · `acuerdo_comercial.finalizado` |

Activar, suspender, finalizar o rescindir. MS-01 consume estos eventos para habilitar o bloquear la publicación de alojamientos del anfitrión.

**Reglas**
- **Un solo acuerdo `ACTIVO` por anfitrión**: lo comprueba el caso de uso y lo respalda el índice único parcial `uq_acuerdo_activo_por_anfitrion` (verificado contra PostgreSQL), que ataja la carrera entre dos peticiones simultáneas.
- Transiciones permitidas: `BORRADOR → {ACTIVO, RESCINDIDO}`, `ACTIVO → {SUSPENDIDO, FINALIZADO, RESCINDIDO}`, `SUSPENDIDO → {ACTIVO, FINALIZADO, RESCINDIDO}`. `FINALIZADO` y `RESCINDIDO` son terminales; nunca se vuelve a `BORRADOR`.
- Suspender y rescindir exigen motivo.
- Una transición inválida no publica ningún evento (la transacción se revierte).
- **Supuesto a confirmar con MS-01:** el documento define 3 eventos para 4 transiciones, así que la rescisión viaja como `acuerdo_comercial.finalizado` con `estado_final: "RESCINDIDO"` en el payload.

### CU-ACU-03 · `GestionarMetodosCobro`

| | |
| --- | --- |
| **Tipo / Disparador** | CRUD · REST (el anfitrión gestiona los suyos) |
| **Archivo** | `src/Acuerdos/Application/UseCase/GestionarMetodosCobro/GestionarMetodosCobroService.php` |
| **Entrada** | `RegistrarMetodoCobroCommand`, `ActualizarMetodoCobroCommand` |
| **Salida** | `MetodoCobro` |

Registrar, actualizar y definir el método de cobro principal (cuenta bancaria, QR o billetera).

**Reglas**
- **El número va cifrado**: se guarda en `numero_cifrado` y en claro solo los `ultimos_digitos`. El cifrado es un detalle de infraestructura detrás del puerto `CifradorDeDatos`; el único archivo que conoce `Illuminate\Support\Facades\Crypt` es `CryptCifradorDeDatos`.
- **Un solo método principal por anfitrión**, respaldado por `uq_metodo_principal`. El primero que registra queda principal automáticamente; los siguientes solo si lo piden, degradando al anterior en la misma transacción.
- Cambiar el número **quita la verificación**: el Administrador aprobó la cuenta anterior, no la nueva.
- La auditoría nunca recibe el número ni el valor cifrado, solo los últimos dígitos.

### CU-ACU-04 · `VerificarMetodoCobro`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · REST (Administrador) |
| **Archivo** | `src/Acuerdos/Application/UseCase/VerificarMetodoCobro/VerificarMetodoCobroHandler.php` |
| **Entrada** | `VerificarMetodoCobroCommand(metodoId, verificadoPor)` |
| **Salida** | `MetodoCobro` |

Marca el método como verificado. **Solo un método principal y verificado sirve para liquidar** (lo exige CU-LIQ-05 antes de aprobar el pago al anfitrión).

---

## 5. Promociones (F09) — 4 casos de uso

### CU-PRO-01 · `GestionarPromociones`

| | |
| --- | --- |
| **Tipo / Disparador** | CRUD · REST |
| **Archivo** | `src/Promociones/Application/UseCase/GestionarPromociones/GestionarPromocionesService.php` |
| **Entrada** | `CrearPromocionCommand`, `ActualizarPromocionCommand`, `AsignarAlojamientosCommand` |
| **Salida** | `Promocion` |

Crear, actualizar, asignar alojamientos (`promocion_alojamiento`), activar, pausar, cancelar y listar. Una promoción nace en `BORRADOR` y se publica con `activar()`. `VENCIDA` y `CANCELADA` son terminales.

**Reglas**
- Un descuento `PORCENTAJE` no puede pasar de 100 % (`chk_promocion_porcentaje_maximo`).
- `vigente_hasta > vigente_desde`.
- Si la promoción tiene alojamientos asignados, solo aplica a esos; sin ninguno, aplica a todos los del anfitrión (o de la plataforma si `anfitrion_id` es `NULL`).

### CU-PRO-02 · `ValidarCuponYCalcularDescuento`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · REST / INT (lo llama CU-PAG-01) |
| **Archivo** | `src/Promociones/Application/UseCase/ValidarCuponYCalcularDescuento/ValidarCuponYCalcularDescuentoHandler.php` |
| **Entrada** | `SolicitudDescuento(subtotal, noches, cupón?, promocionId?, alojamientoId?, anfitrionId?, fecha?)` |
| **Salida** | `DescuentoAplicado(promocionId, descuentoCentavos, moneda, financiamiento)` o `null` |

Valida fechas, `min_noches` y tope de usos, y devuelve el descuento calculado. Este handler **implementa el puerto `CalculadorDeDescuento`** que consume el paquete `Pagos`.

**Reglas**
- Un cupón o una promoción pedidos explícitamente que no aplican lanzan `DomainException` **con el motivo exacto** (vencido, pausado, sin usos, por debajo de las noches mínimas, inexistente): ignorar en silencio un código tecleado cotizaría un total que el cliente no espera.
- Las promociones automáticas (sin código) que no aplican se omiten en silencio y devuelve `null`.
- Entre varias automáticas aplicables **gana la de mayor descuento**; los cupones nunca se aplican solos.
- Un descuento `MONTO_FIJO` **no puede superar el subtotal**.
- Un `PORCENTAJE` se calcula con `Money::porPorcentaje()`, con redondeo de medio centavo hacia arriba.

### CU-PRO-03 · `RegistrarUsoDePromocion`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · INT (evento de dominio `PagoCapturado`) |
| **Archivo** | `src/Promociones/Application/UseCase/RegistrarUsoDePromocion/RegistrarUsoDePromocionHandler.php` |
| **Entrada** | `RegistrarUsoDePromocionCommand(promocionId, reservaId?, pagoId?)` |
| **Salida** | `Promocion` |
| **Eventos** | `promocion.aplicada` (lo consume MS-04 para F11) |

Incrementa `usos_actuales` y emite el evento, todo dentro de una transacción.

**Reglas**
- Si ya se alcanzó `max_usos`, falla con `DomainException` en vez de pasarse; además lo ataja el `CHECK chk_promocion_usos`.
- No exige que la promoción esté `ACTIVA` (una pausada después de cotizar no debe romper un cobro ya capturado), pero sí rechaza `CANCELADA`.
- **Pendiente:** el consumidor de RabbitMQ que lo invoque debe descartar los mensajes repetidos con el inbox (`evento_procesado`); este caso de uso no es idempotente por sí mismo.

### CU-PRO-04 · `VencerPromociones`

| | |
| --- | --- |
| **Tipo / Disparador** | JOB · JOB |
| **Archivo** | `src/Promociones/Application/UseCase/VencerPromociones/VencerPromocionesHandler.php` |
| **Entrada** | `VencerPromocionesCommand(fecha?)` |
| **Salida** | `list<string>` ids de las que pasaron a `VENCIDA` |

Pasa a `VENCIDA` las promociones cuya `vigente_hasta` ya pasó. La fecha sale del puerto `Clock`, nunca de `now()` directo, para que la regla se pueda probar sin esperar al calendario.

---

## 6. Pagos (F05) — 12 casos de uso

Es el paquete donde está el dinero. Dos reglas de orden valen para todo el paquete:

1. **La llamada a la pasarela va fuera de la transacción de base de datos.** Una llamada HTTP lenta no debe tener abierta una transacción.
2. **El cambio de estado, la transacción registrada y el evento van dentro de una sola transacción** (patrón Outbox). Así nunca se publica `pago.capturado` de un pago que no quedó guardado, ni queda un pago capturado del que nadie se enteró.

### CU-PAG-01 · `CotizarReserva`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · REST (MS-02) |
| **Archivo** | `src/Pagos/Application/UseCase/CotizarReserva/CotizarReservaHandler.php` |
| **Entrada** | `CotizarReservaCommand(anfitrionId, subtotal, noches, alojamientoId?, categoría?, cupón?, promocionId?, fecha?)` |
| **Salida** | `Cotizacion(desglose, promocionId?, acuerdoId?)` |

Primer paso de la saga de reserva: devuelve subtotal, descuento, cargo de servicio y total. **Es de solo lectura: no crea ningún pago.**

**Reglas**
- No consulta `ref_reserva` a propósito: cuando MS-02 cotiza, la reserva todavía no está confirmada y el evento `reserva.confirmada` no llegó, así que los datos del alojamiento y las noches vienen en el comando.
- Toma la tarifa negociada del acuerdo `ACTIVO` del anfitrión si existe.

### CU-PAG-02 · `CrearPago`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · REST con cabecera `Idempotency-Key` |
| **Archivo** | `src/Pagos/Application/UseCase/CrearPago/CrearPagoHandler.php` |
| **Entrada** | `CrearPagoCommand(reservaId, clienteId, anfitrionId, subtotal, descuento, cargoServicio, método, idempotencyKey, sagaId?, promocionId?)` |
| **Salida** | `Pago` en estado `PENDIENTE` |

**Reglas**
- **Idempotente por `Idempotency-Key`**: si la clave ya existe devuelve el pago que ya se creó, en vez de crear otro. En la saga de reserva esa clave es el `saga_id`. La columna `idempotency_key` es `UNIQUE`, así que la base es la última línea de defensa si dos reintentos llegan a la vez (verificado contra PostgreSQL).
- **Una reserva no puede tener dos pagos vivos** (`PENDIENTE`, `AUTORIZADO`, `CAPTURADO`): respaldado por `uq_pago_vivo_por_reserva`. Un pago `CANCELADO` o `FALLIDO` libera la reserva.
- El descuento no puede superar el subtotal; `monto_total = subtotal − descuento + cargo_servicio` lo garantiza también el `CHECK chk_pago_desglose`.

### CU-PAG-03 · `CobrarPago`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · REST / INT |
| **Archivo** | `src/Pagos/Application/UseCase/CobrarPago/CobrarPagoHandler.php` |
| **Entrada** | `CobrarPagoCommand(pagoId, idempotencyKey?)` |
| **Salida** | `Pago` `CAPTURADO` o `FALLIDO` |
| **Eventos** | `pago.capturado` o `pago.fallido` |

El paso de la saga donde MS-02 pide el cobro, y el ejemplo que usa el documento de arquitectura.

**Reglas**
- Un pago ya capturado **no se vuelve a cobrar**: el reintento devuelve el mismo pago, sin llamar otra vez a la pasarela ni publicar el evento dos veces.
- Le pasa a la pasarela la **misma** `Idempotency-Key` del pago, para que tampoco cobre doble del otro lado.
- Si la pasarela rechaza, el pago queda `FALLIDO`, se emite `pago.fallido` para que MS-02 libere la disponibilidad, y **el intento fallido queda registrado** en `transaccion_pago`.

### CU-PAG-04 · `CancelarPagoPendiente`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · REST / JOB |
| **Archivo** | `src/Pagos/Application/UseCase/CancelarPagoPendiente/CancelarPagoPendienteHandler.php` |
| **Entrada** | `CancelarPagoPendienteCommand(pagoId, motivo)` |
| **Salida** | `Pago` `CANCELADO` |
| **Eventos** | `pago.cancelado` |

Dos usos: compensación de la saga cuando falla otro paso, y expiración de un pago que quedó `PENDIENTE` demasiado tiempo (el JOB recorre `PagoRepository::pendientesAnterioresA()`, con la ventana de `config('comercial.pagos.horas_para_expirar_pendiente')`).

**Reglas**
- Solo se cancela un pago `PENDIENTE` o `AUTORIZADO`; uno capturado se devuelve con un reembolso, no se cancela.
- Cancelar libera la reserva para el índice `uq_pago_vivo_por_reserva`, así que después se puede crear otro pago para la misma reserva.

### CU-PAG-05 · `RecibirNotificacionPasarela`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · REST (webhook) |
| **Archivo** | `src/Pagos/Application/UseCase/RecibirNotificacionPasarela/RecibirNotificacionPasarelaHandler.php` |
| **Entrada** | `RecibirNotificacionPasarelaCommand(pagoId, referenciaPasarela, exitosa, pasarela?, motivo?, monto?, tipo?, payload)` |
| **Salida** | `ResultadoNotificacion(referencia, aplicada, cambioElEstado)` |

La pasarela avisa por su cuenta, puede avisar tarde y puede avisar dos veces.

**Reglas**
- **El filtro de repetición es la `referencia_pasarela`**, que es `UNIQUE` en `transaccion_pago`: si ya existe una transacción con esa referencia, el webhook se descarta sin tocar nada.
- Un pago ya cerrado no cambia de estado por un webhook atrasado, pero la transacción **sí** se registra: el libro con la pasarela es append-only y la conciliación la necesita.
- También cubre el caso en que el cobro se confirma por webhook y no por la respuesta síncrona de CU-PAG-03.

### CU-PAG-06 · `ConsultarPagos`

| | |
| --- | --- |
| **Tipo / Disparador** | QRY · REST |
| **Archivo** | `src/Pagos/Application/UseCase/ConsultarPagos/ConsultarPagosService.php` |
| **Entrada** | `pagoId`, o `CriterioDePagos(clienteId?, anfitrionId?, reservaId?, estado?, desde?, hasta?, límite)` |
| **Salida** | `DetalleDePago` (pago, transacciones, reembolsos, comprobante, total reembolsado, saldo reembolsable) o `list<Pago>` |

Detalle, listados y descarga del comprobante. **Los filtros por rol los resuelve la capa de Presentación** con el JWT de Keycloak (el cliente ve los suyos, el anfitrión los de sus alojamientos, el Administrador todos) y los pasa ya armados en el criterio.

### CU-PAG-07 · `SolicitarReembolso`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · EVT (`reserva.cancelada`) |
| **Archivo** | `src/Pagos/Application/UseCase/SolicitarReembolso/SolicitarReembolsoHandler.php` |
| **Entrada** | `SolicitarReembolsoCommand(reservaId, motivo, fechaCancelacion?, montoForzado?, detalle?, solicitadoPor?)` |
| **Salida** | `Reembolso` `SOLICITADO`, o `null` si no corresponde devolver nada |

Crea el reembolso con el monto que manda la política de cancelación. **No mueve dinero todavía.**

**Reglas**
- Las fechas salen de la copia local `ref_reserva`, no de una llamada a MS-02: esto funciona aunque MS-02 esté caído.
- Nunca se solicita más de lo que queda por devolver (`total − ya reembolsado`).
- Si la política dice que no corresponde nada, **no se crea un reembolso de cero**.
- **SUPUESTO a confirmar con el negocio** (`src/Pagos/Domain/PoliticaDeReembolso.php`): el documento del proyecto no define los tramos. Los implementados son 100 % con 7 días o más de anticipación, 50 % entre 3 y 6 días, 0 % con menos de 3 días o ya empezada la estadía; y 100 % si la cancelación no es del cliente. Están en una sola clase para que cambiarlos sea cambiar una clase y sus pruebas.

### CU-PAG-08 · `AprobarReembolso`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · REST (Administrador) |
| **Archivo** | `src/Pagos/Application/UseCase/AprobarReembolso/AprobarReembolsoHandler.php` |
| **Entrada** | `AprobarReembolsoCommand(reembolsoId, aprobado, revisadoPor, detalle?)` |
| **Salida** | `Reembolso` `APROBADO` o `RECHAZADO` |

Decisión humana, para disputas y casos manuales. Queda auditada con el nombre de quien la tomó. Un rechazo exige explicación. Solo se decide sobre un reembolso `SOLICITADO`.

### CU-PAG-09 · `ProcesarReembolso`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · INT / JOB |
| **Archivo** | `src/Pagos/Application/UseCase/ProcesarReembolso/ProcesarReembolsoHandler.php` |
| **Entrada** | `ProcesarReembolsoCommand(reembolsoId, idempotencyKey?)` |
| **Salida** | `Reembolso` `PROCESADO` |
| **Eventos** | `reembolso.procesado` |

Devuelve el dinero en la pasarela. MS-02 marca la reserva y Liquidaciones revierte la comisión (CU-LIQ-02).

**Reglas**
- No se reembolsa más que el total cobrado: lo comprueba el caso de uso con el acumulado y lo protege la entidad `Pago`.
- Un reembolso parcial deja el pago en `REEMBOLSADO_PARCIAL`; el que completa el total, en `REEMBOLSADO`.
- Si la pasarela falla, **el reembolso queda `APROBADO` (no `PROCESADO`)** y la transacción `FALLIDA` se registra en su propia transacción de base de datos, para que el rollback no borre el rastro del intento. El JOB lo reintenta y nunca se emite el evento de un reembolso que no ocurrió.
- Reprocesar un reembolso ya `PROCESADO` lo devuelve tal cual.

### CU-PAG-10 · `EmitirComprobante`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · INT (evento `PagoCapturado`) |
| **Archivo** | `src/Pagos/Application/UseCase/EmitirComprobante/EmitirComprobanteHandler.php` |
| **Entrada** | `EmitirComprobanteCommand(pagoId, tipo, nitCliente?, razonSocial?)` |
| **Salida** | `Comprobante` |

Recibo o factura con número único, por el puerto `NumeradorComprobantes` (correlativo por tipo y año: `RECIBO-2026-000123`, tomado con `FOR UPDATE`).

**Reglas**
- **Idempotente**: si el pago ya tiene comprobante se devuelve ese, porque `pago.capturado` puede llegar dos veces y `numero` es `UNIQUE`.
- Solo se factura lo que se cobró: un pago no capturado no genera comprobante.
- Una `FACTURA` exige el NIT del cliente.
- El PDF lo genera la infraestructura; el dominio guarda la URL y un SHA-256 de 64 caracteres para poder probar después que el archivo no cambió.

### CU-PAG-11 · `EjecutarConciliacion`

| | |
| --- | --- |
| **Tipo / Disparador** | JOB · JOB diario (también REST) |
| **Archivo** | `src/Pagos/Application/UseCase/EjecutarConciliacion/EjecutarConciliacionHandler.php` |
| **Entrada** | `EjecutarConciliacionCommand(fechaCorte?, ejecutadaPor?, moneda)` |
| **Salida** | `Conciliacion` con sus `ConciliacionItem` |

Compara las transacciones `EXITOSA` del corte con el reporte de la pasarela. **La comparación es regla de negocio y vive en la entidad `Conciliacion`**, así se puede probar con listas en memoria.

**Reglas**
- `COINCIDE` si la referencia está en los dos lados con el mismo monto; `DIFERENCIA_MONTO` si difieren; `NO_REGISTRADA` si la pasarela reporta algo que MS-03 no tiene.
- Una transacción nuestra que la pasarela **no** reportó se guarda como `DIFERENCIA_MONTO` con `monto_pasarela = 0.00`, porque el esquema solo admite esos tres resultados.
- La conciliación queda `CONCILIADA` solo si todos los ítems coinciden y la diferencia es cero; si no, `CON_DIFERENCIAS`.
- `diferencia` es columna generada (`total_pasarela − total_sistema`), así que nadie puede guardar una que no cuadre.
- La base no admite dos conciliaciones del mismo corte (`UNIQUE (pasarela, fecha_corte)`): repetir el job devuelve la que ya existe.

### CU-PAG-12 · `RevisarDiferenciaConciliacion`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · REST (Administrador) |
| **Archivo** | `src/Pagos/Application/UseCase/RevisarDiferenciaConciliacion/RevisarDiferenciaConciliacionHandler.php` |
| **Entrada** | `RevisarDiferenciaConciliacionCommand(conciliacionId, referenciaPasarela, observación, revisadoPor, transaccionId?, cerrar?)` |
| **Salida** | `Conciliacion` |

Explica un ítem con diferencia y, si hace falta, lo liga a la transacción correcta.

**Reglas**
- La revisión exige observación: una diferencia de dinero explicada por nadie no sirve de nada.
- La conciliación **solo se cierra** cuando cada ítem que no coincide tiene su observación.
- Dentro de una conciliación los ítems se identifican por su `referencia_pasarela`, que es única.

---

## 7. Liquidaciones (F05 + F16) — 10 casos de uso

Toda escritura de dinero aquí lleva su **asiento contable**, y los asientos los construye `src/Liquidaciones/Domain/Asiento.php`, que **no deja construir un asiento descuadrado**: `SUM(DEBITO) = SUM(CREDITO)`. Es la regla que la base de datos no puede expresar en un `CHECK`.

Plan de cuentas: `PASARELA`, `INGRESO_COMISION`, `INGRESO_CARGO_SERVICIO`, `POR_PAGAR_ANFITRION`, `REEMBOLSOS`, `PROMOCIONES_PLATAFORMA`.

### CU-LIQ-01 · `RegistrarComision`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · INT (evento `PagoCapturado`) |
| **Archivo** | `src/Liquidaciones/Application/UseCase/RegistrarComision/RegistrarComisionHandler.php` |
| **Entrada** | `RegistrarComisionCommand(pagoId, reservaId, anfitrionId, subtotal, total, descuento, financiamiento, categoría?, nivel?, fecha?)` |
| **Salida** | `Comision` `RETENIDA` |

Crea la comisión con la **tarifa aplicada como snapshot** y escribe el asiento del cobro: debita `PASARELA` por el total y acredita comisión, cargo de servicio y lo que queda por pagarle al anfitrión. Las dos cosas en la misma transacción: una comisión sin asiento dejaría el libro sin cuadrar.

**Reglas**
- **Idempotente**: `pago_id` es `UNIQUE` en `comision`, y el evento puede llegar dos veces.
- Recalcula con las mismas reglas de CU-MON-02; no confía en un monto que venga de afuera.
- Guarda `porcentaje_aplicado`: si mañana cambia la regla de monetización, el histórico no se mueve.
- **Pendiente de integración:** `financiada_por` no viaja hoy en el payload de `pago.capturado` (el pago solo guarda `promocion_id`). Por omisión se asume que el descuento lo financia el anfitrión, que es el valor por omisión de la columna. El consumidor de Presentación debe resolverlo con `promocion_id` y pasarlo.

### CU-LIQ-02 · `RevertirComision`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · INT (`reembolso.procesado`) |
| **Archivo** | `src/Liquidaciones/Application/UseCase/RevertirComision/RevertirComisionHandler.php` |
| **Entrada** | `RevertirComisionCommand(pagoId, esTotal)` |
| **Salida** | `ResultadoReversion(comision?, revertida, requiereAjusteEnLaSiguienteLiquidacion, detalle)` |

Tres casos, y ninguno reescribe el pasado:

1. La comisión está `RETENIDA` o `LIQUIDABLE` → se marca `REVERTIDA` y se escribe el asiento de reversa.
2. La comisión ya está `LIQUIDADA` (al anfitrión ya se le pagó) → **no se toca**, se escribe el asiento de reversa y el monto queda para descontarse de la liquidación siguiente.
3. El reembolso fue parcial → la comisión entera no corresponde revertirla; el ajuste va también en la siguiente.

En los casos 2 y 3 el resultado lo dice con `requiereAjusteEnLaSiguienteLiquidacion`. **Decisión pendiente:** hoy ese descuento se pasa a mano a CU-LIQ-04, porque el esquema tiene la columna `total_reembolsos` pero no una tabla que diga qué reembolso pertenece a qué liquidación. Es de la misma familia que la decisión #4 del documento de arquitectura (`aplicacion_promocion`).

### CU-LIQ-03 · `MarcarComisionesLiquidables`

| | |
| --- | --- |
| **Tipo / Disparador** | EVT/JOB · EVT (`estadia.completada`) / JOB |
| **Archivo** | `src/Liquidaciones/Application/UseCase/MarcarComisionesLiquidables/MarcarComisionesLiquidablesHandler.php` |
| **Entrada** | `MarcarComisionesLiquidablesCommand(reservaId, diasLiquidacion?)` |
| **Salida** | `list<string>` ids de las comisiones marcadas |

La estadía terminó, así que la comisión pasa de `RETENIDA` a `LIQUIDABLE` con fecha **`check-out + dias_liquidacion` del acuerdo**. Ese plazo es la ventana para disputas y contracargos: antes de que venza, el dinero no se transfiere.

**Reglas**
- Las fechas salen de `ref_reserva`, no de una llamada a MS-02.
- Sin acuerdo `ACTIVO` se usa el plazo por omisión (7 días, igual que el `DEFAULT` de la columna).
- Una comisión `REVERTIDA` no se vuelve liquidable.

### CU-LIQ-04 · `GenerarLiquidaciones`

| | |
| --- | --- |
| **Tipo / Disparador** | JOB · JOB periódico |
| **Archivo** | `src/Liquidaciones/Application/UseCase/GenerarLiquidaciones/GenerarLiquidacionesHandler.php` |
| **Entrada** | `GenerarLiquidacionesCommand(hasta?, periodoDesde?, moneda, ajustesPorReembolso)` |
| **Salida** | `list<Liquidacion>` `GENERADA` |

Una liquidación por anfitrión, juntando sus comisiones `LIQUIDABLE` cuyo plazo ya se cumplió, y ligándolas a ella (`LIQUIDADA`).

**Reglas**
- **Recorre `ref_anfitrion`, no MS-01**: si MS-01 está caído esa noche la liquidación igual corre, y un anfitrión suspendido simplemente no aparece entre los verificados. Es exactamente para lo que existe el paquete `Referencias`.
- No se generan filas vacías: un anfitrión sin comisiones liquidables, o cuyo neto quedaría en cero, no genera liquidación y sus comisiones quedan para la corrida siguiente.
- Un neto negativo (reembolsos mayores que lo que se le debe) **detiene el caso de uso con un error explícito** para revisar a mano, en vez de guardar una deuda al revés.
- `total_neto = total_bruto − total_comisiones − total_reembolsos` lo garantiza además el `CHECK chk_liquidacion_neto`.
- Toma el `metodo_id` principal y verificado del anfitrión.

### CU-LIQ-05 · `AprobarLiquidacion`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · REST (Administrador) |
| **Archivo** | `src/Liquidaciones/Application/UseCase/AprobarLiquidacion/AprobarLiquidacionHandler.php` |
| **Entrada** | `AprobarLiquidacionCommand(liquidacionId, aprobadoPor)` |
| **Salida** | `Liquidacion` `APROBADA` |

Decisión antes de que salga el dinero. **Vuelve a comprobar dos cosas que pueden haber cambiado** desde que el JOB generó la liquidación: que el anfitrión siga habilitado y que su método de cobro siga verificado. Sin método verificado no se aprueba.

### CU-LIQ-06 · `RechazarLiquidacion`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · REST |
| **Archivo** | `src/Liquidaciones/Application/UseCase/RechazarLiquidacion/RechazarLiquidacionHandler.php` |
| **Entrada** | `RechazarLiquidacionCommand(liquidacionId, revisadoPor, motivo, anular?)` |
| **Salida** | `Liquidacion` `RECHAZADA` o `ANULADA` |

Rechaza o anula, y **libera las comisiones**: vuelven a `LIQUIDABLE` y entran en la corrida siguiente. Si no se liberaran, ese dinero quedaría atrapado en una liquidación muerta y el anfitrión nunca lo cobraría. Exige motivo. Una liquidación `PAGADA` no se rechaza ni se anula: el dinero ya salió y eso se corrige con un asiento de reversa.

### CU-LIQ-07 · `PagarLiquidacion`

| | |
| --- | --- |
| **Tipo / Disparador** | NEG · REST |
| **Archivo** | `src/Liquidaciones/Application/UseCase/PagarLiquidacion/PagarLiquidacionHandler.php` |
| **Entrada** | `PagarLiquidacionCommand(liquidacionId, referenciaTransferencia, pagadoPor)` |
| **Salida** | `Liquidacion` `PAGADA` |
| **Eventos** | `liquidacion.pagada` (MS-01 avisa al anfitrión) |

Registra la transferencia, escribe el asiento que cancela la deuda (`POR_PAGAR_ANFITRION` al débito, `PASARELA` al crédito) y emite el evento. Todo en una transacción: no puede quedar una liquidación `PAGADA` sin asiento, ni un evento publicado de una transferencia que no se guardó.

**Reglas**
- Solo se paga una liquidación `APROBADA`, y exige la referencia de la transferencia.
- Pagar dos veces la misma liquidación devuelve la que ya está pagada, sin duplicar el asiento ni el evento.

### CU-LIQ-08 · `RetenerLiquidacionesDeAnfitrion`

| | |
| --- | --- |
| **Tipo / Disparador** | EVT · EVT (`anfitrion.suspendido`) |
| **Archivo** | `src/Liquidaciones/Application/UseCase/RetenerLiquidacionesDeAnfitrion/RetenerLiquidacionesDeAnfitrionHandler.php` |
| **Entrada** | `RetenerLiquidacionesDeAnfitrionCommand(anfitrionId, motivo)` |
| **Salida** | `list<string>` ids de las liquidaciones retenidas |

Pausa el dinero de un anfitrión suspendido por MS-01.

**Decisión de diseño:** el esquema **no tiene un estado "RETENIDA"** para `liquidacion`, así que "pausar" se implementa como anular las liquidaciones que todavía no se pagaron y liberar sus comisiones, que vuelven a `LIQUIDABLE`. Es reversible por construcción: cuando MS-01 reactive al anfitrión, `verificados()` lo vuelve a incluir y la siguiente corrida de CU-LIQ-04 regenera su liquidación con las mismas comisiones. Una liquidación ya `PAGADA` no se toca.

### CU-LIQ-09 · `ConsultarLiquidacionesYSaldos`

| | |
| --- | --- |
| **Tipo / Disparador** | QRY · REST |
| **Archivo** | `src/Liquidaciones/Application/UseCase/ConsultarLiquidacionesYSaldos/ConsultarLiquidacionesYSaldosService.php` |
| **Entrada** | `anfitrionId` / `liquidacionId` / `(anfitrionId, hasta?)` |
| **Salida** | `list<Liquidacion>`, `DetalleDeLiquidacion`, `Money` (saldo), `list<Comision>` (por liquidar) |

Historial, detalle de las comisiones de una liquidación, saldo y lo que entraría en la próxima corrida. **El saldo sale del libro contable** (créditos menos débitos de `POR_PAGAR_ANFITRION`), no de sumar liquidaciones: el libro es la fuente de verdad y ya incluye las reversas.

### CU-LIQ-10 · `ReportesFinancieros`

| | |
| --- | --- |
| **Tipo / Disparador** | QRY · REST (lo usa también MS-04) |
| **Archivo** | `src/Liquidaciones/Application/UseCase/ReportesFinancieros/ReportesFinancierosService.php` |
| **Entrada** | `(desde, hasta, moneda)` |
| **Salida** | `ResumenDeIngresos` · `list<IngresoPorCategoria>` |

Ingresos y comisiones por período y por categoría. Las agregaciones (`SUM`, `GROUP BY`) viven detrás del puerto `ConsultaDeReportes`, implementado en Infraestructura: cargar entidades para sumarlas en PHP sería caro y sin sentido, y la capa de Aplicación sigue sin saber SQL.

**Reglas**
- Solo cuenta pagos `CAPTURADO`, `REEMBOLSADO_PARCIAL` o `REEMBOLSADO`: un pago fallido o cancelado no es ingreso de nadie.
- Los reembolsos se cuentan por separado, con los que ya están `PROCESADO`.
- La categoría sale de `ref_reserva`, que es justamente para lo que existe: agrupar sin preguntarle nada a MS-01 ni a MS-02.
- El período no puede terminar antes de empezar.
- Lo consumen el panel del Administrador, F11 (tarifa sugerida) y F13 (copiloto), siempre de solo lectura.

---

## 8. Referencias y Shared — 3 casos de uso

### CU-REF-01 · `SincronizarAnfitrion`

| | |
| --- | --- |
| **Tipo / Disparador** | EVT · EVT (`anfitrion.verificado` / `anfitrion.suspendido`) |
| **Archivo** | `src/Referencias/Application/UseCase/SincronizarAnfitrion/SincronizarAnfitrionHandler.php` |
| **Entrada** | `SincronizarAnfitrionCommand(anfitrionId, nombreCompleto, estado, nit?, razonSocial?)` |
| **Salida** | `AnfitrionReference` |

Upsert en `ref_anfitrion`. Sin esta copia, liquidar de noche obligaría a preguntarle a MS-01 por REST el estado de cada anfitrión, y si MS-01 está caído la liquidación falla o se atrasa.

### CU-REF-02 · `SincronizarReserva`

| | |
| --- | --- |
| **Tipo / Disparador** | EVT · EVT (`reserva.confirmada` / `reserva.actualizada`) |
| **Archivo** | `src/Referencias/Application/UseCase/SincronizarReserva/SincronizarReservaHandler.php` |
| **Entrada** | `SincronizarReservaCommand(reservaId, clienteId, anfitrionId, alojamientoId, checkin, checkout, estado, categoría?)` |
| **Salida** | `ReservaReference` |

Upsert en `ref_reserva`. Pagos y Liquidaciones leen de aquí las fechas y el estado sin llamar a MS-02. `noches` es columna generada por PostgreSQL (verificado: check-in 2026-11-01 y check-out 2026-11-05 dan 4).

### CU-SHA-01 · `PublicarEventosOutbox`

| | |
| --- | --- |
| **Tipo / Disparador** | JOB · JOB (cada pocos segundos) |
| **Archivo** | `src/Shared/Application/UseCase/PublicarEventosOutbox/PublicarEventosOutboxHandler.php` |
| **Entrada** | `PublicarEventosOutboxCommand(lote)` |
| **Salida** | `ResultadoPublicacion(publicados, fallidos)` |

Relay del patrón Outbox: toma los `outbox_evento` con `publicado_en IS NULL` y los envía al broker. Corre **aparte** de la transacción de negocio, por eso el cambio de estado y su evento nunca quedan en desacuerdo.

**Reglas**
- Un broker caído no detiene el lote ni pierde el evento: la fila sigue pendiente, se suma un intento y se reintenta en la próxima corrida.
- Entrega *at-least-once*: si el envío sale bien pero el proceso cae antes de marcar la fila, el evento se reenvía. Los consumidores lo descartan con el inbox (`evento_procesado`).

La **auditoría** (`auditoria_log`) y el **filtro de idempotencia** del inbox no son casos de uso: son servicios transversales que usan los handlers, detrás de los puertos `AuditLogger` e `InboxRegistry`.

---

## 9. Eventos de dominio que publica MS-03

Todos salen por Outbox, dentro de la transacción del caso de uso que los emite.

| Evento | Lo emite | Lo consume |
| --- | --- | --- |
| `pago.capturado` | CU-PAG-03, CU-PAG-05 | MS-02 (confirma la reserva), MS-04 (F11), e internamente CU-LIQ-01, CU-PRO-03 y CU-PAG-10 |
| `pago.fallido` | CU-PAG-03, CU-PAG-05 | MS-02 (compensa: libera la disponibilidad) |
| `pago.cancelado` | CU-PAG-04 | MS-02 (compensación de la saga o expiración) |
| `reembolso.procesado` | CU-PAG-09 | MS-02 (marca la reserva), CU-LIQ-02 |
| `promocion.aplicada` | CU-PRO-03 | MS-04 (F11) |
| `acuerdo_comercial.activado` · `.suspendido` · `.finalizado` | CU-ACU-02 | MS-01 (habilita o bloquea publicaciones) |
| `liquidacion.pagada` | CU-LIQ-07 | MS-01 (avisa al anfitrión) |

Eventos que MS-03 **consume** (los adaptadores están pendientes, ver sección 10): `reserva.confirmada`, `reserva.actualizada`, `reserva.cancelada`, `estadia.completada` de MS-02; `anfitrion.verificado`, `anfitrion.suspendido` de MS-01.

Los nombres de eventos y endpoints son la propuesta del documento de arquitectura y **hay que acordarlos con los otros equipos** (decisión pendiente #6 de ese documento).

---

## 10. Qué falta y qué está verificado

### Pendiente por decisión del alcance

La base de Presentation (P1) existe: JWT de Keycloak, idempotencia, errores y `/api/v1/salud`. Falta, por paquete:

- `Presentation/Http`: controladores, rutas, Form Requests y Resources para los disparadores `REST`, más el middleware que valida el JWT de Keycloak y autoriza por rol (`ADMIN`, `ANFITRION`, `CLIENTE`).
- `Presentation/Messaging`: consumidores de RabbitMQ para los disparadores `EVT`, que antes de procesar deben descartar los mensajes repetidos con el `InboxRegistry`.
- Comandos de Artisan y entradas del Scheduler para los disparadores `JOB` (`outbox:publish`, vencer promociones, generar liquidaciones, conciliación diaria, expirar pagos pendientes).

Los puertos que esa capa usará ya están definidos y enlazados en `app/Providers/ComercialServiceProvider.php`, así que agregarla es escribir adaptadores delgados, sin tocar ningún caso de uso.

### Decisiones pendientes anotadas en el código

| # | Qué | Dónde |
| --- | --- | --- |
| 1 | Tramos de la política de reembolso (supuesto, confirmar con el negocio) | `src/Pagos/Domain/PoliticaDeReembolso.php` |
| 2 | `financiada_por` no viaja en el payload de `pago.capturado` | `RegistrarComisionCommand` |
| 3 | Descontar un reembolso de la liquidación siguiente se pasa a mano; faltaría una tabla de ajustes | `RevertirComisionHandler`, `GenerarLiquidacionesCommand` |
| 4 | Sin tabla `aplicacion_promocion`, el detalle de qué reserva usó qué promoción solo queda en el payload de `promocion.aplicada` | decisión #4 del documento de arquitectura |
| 5 | Nombre del evento de rescisión de acuerdo (hoy `.finalizado` con `estado_final`) | `CU-ACU-02` |
| 6 | `transaccion_pago`, `reembolso` y `comprobante` no tienen columna de moneda: la heredan del pago | repositorios de `Pagos` |
| 7 | El adaptador real de RabbitMQ y de la pasarela de pagos (hoy `LogMessageBroker` y `PasarelaPagoSimulada`) | `config/comercial.php` |

### Defecto encontrado en el DDL de `context/`

`ms03_comercial_financiero.sql` declara `conciliacion.estado VARCHAR(12)`, pero su propio `CHECK` admite `'CON_DIFERENCIAS'`, que tiene **15 caracteres**: cualquier conciliación con diferencias fallaba al guardarse con `value too long for type character varying(12)`. La migración lo corrige a `VARCHAR(20)`. Lo detectó la prueba de integración `test_la_diferencia_de_una_conciliacion_es_una_columna_generada`.

### Verificación ejecutada

| Qué | Cómo | Resultado |
| --- | --- | --- |
| Dominio y casos de uso | `php vendor/bin/phpunit` (suites `Unit`, `Application`, `Architecture`) | **233 pruebas, 584 aserciones, 0 fallos** (248 en total; las 15 de `Integration` se omiten sin PostgreSQL) |
| Regla de dependencias | `tests/Architecture/ReglaDeDependenciasTest.php` | 6 pruebas; comprobado que **falla** al importar `Illuminate\Support\Str` en `src/Pagos/Domain/Pago.php` |
| Enlaces del contenedor | `tests/Architecture/ContenedorResuelveLosEnlacesTest.php` | los 29 puertos y 6 casos de uso que cruzan paquetes se resuelven |
| Migraciones | `docker compose up -d` + `php artisan migrate` contra PostgreSQL 16 | 10 migraciones, **19 tablas** en el esquema `comercial` |
| Restricciones de la base | `DB_CONNECTION=pgsql … phpunit --testsuite=Integration` | **15 pruebas**: rechaza totales que no cuadran, dos pagos vivos por reserva, `idempotency_key` repetida, dos acuerdos activos, neto de liquidación incoherente, `UPDATE`/`DELETE` en las tablas append-only |
| Sintaxis | `php -l` sobre los 220 archivos de `src/` | sin errores |

Las pruebas de la suite `Integration` se omiten solas si no hay PostgreSQL a mano, así que la suite por omisión corre en cualquier máquina. La suite `Contract` (eventos y REST compartidos con MS-01, MS-02 y MS-04) tiene su carpeta creada pero ningún caso: depende de acordar los contratos con los otros equipos.
