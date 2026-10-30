<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ProcesarPago;

use Src\Comercial\Application\Port\CalculadorDeDescuento;
use Src\Comercial\Application\Port\SolicitudDescuento;
use Src\Financiero\Application\Port\ComprobanteRepository;
use Src\Financiero\Application\Port\NumeradorComprobantes;
use Src\Financiero\Application\Port\PagoRepository;
use Src\Financiero\Application\Port\PasarelaPago;
use Src\Financiero\Application\Port\TransaccionPagoRepository;
use Src\Financiero\Application\Response\ComprobanteResponse;
use Src\Financiero\Application\Response\CotizacionResponse;
use Src\Financiero\Application\Response\PagoConTotalResponse;
use Src\Financiero\Domain\Entity\Comprobante;
use Src\Financiero\Domain\Entity\Pago;
use Src\Financiero\Domain\Entity\TransaccionPago;
use Src\Financiero\Domain\Enum\EstadoTransaccion;
use Src\Financiero\Domain\Enum\TipoTransaccion;
use Src\Monetizacion\Application\Port\CalculadorDeDesglose;
use Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial\CalcularDesgloseComercialRequest;
use Src\Monetizacion\Domain\Enum\FinanciamientoDescuento;
use Src\Relaciones\Application\Port\AcuerdoComercialQuery;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Money;

/** Caso de uso ProcesarPago: implementa la interfaz de entrada ProcesarPagoUseCase. */
final class ProcesarPagoService implements ProcesarPagoUseCase
{
    public function __construct(
        private readonly CalculadorDeDesglose $calcularDesglose,
        private readonly CalculadorDeDescuento $promociones,
        private readonly AcuerdoComercialQuery $acuerdos,
        private readonly Clock $clock,
        private readonly PagoRepository $pagos,
        private readonly UuidGenerator $uuid,
        private readonly TransactionManager $tx,
        private readonly TransaccionPagoRepository $transacciones,
        private readonly PasarelaPago $pasarela,
        private readonly EventPublisher $eventos,
        private readonly ComprobanteRepository $comprobantes,
        private readonly NumeradorComprobantes $numerador,
    ) {}

    /**
     * Disparador: REST desde MS-02.
     *
     * Primer paso de la saga de reserva: devuelve el desglose que vera el cliente
     * -- subtotal, descuento, cargo de servicio y total -- sin crear ningun pago ni
     * tocar nada. Es una operacion de solo lectura.
     *
     * No consulta `ref_reserva` a proposito: cuando MS-02 cotiza, la reserva
     * todavia no esta confirmada y el evento `reserva.confirmada` no llego, asi que
     * los datos del alojamiento y las fechas vienen en el comando.
     */
    public function cotizar(CotizarReservaRequest $comando): CotizacionResponse
    {
        $fecha = $comando->fecha ?? $this->clock->hoy();

        $descuento = $this->promociones->descuentoAplicable(new SolicitudDescuento(
            subtotalCentavos: $comando->subtotalCentavos,
            noches: $comando->noches,
            moneda: $comando->moneda,
            codigoCupon: $comando->codigoCupon,
            promocionId: $comando->promocionId,
            alojamientoId: $comando->alojamientoId,
            anfitrionId: $comando->anfitrionId,
            fecha: $fecha,
        ));

        // La tarifa negociada del acuerdo ACTIVO gana sobre la regla general.
        $acuerdo = $this->acuerdos->acuerdoActivoDe($comando->anfitrionId);

        $desglose = $this->calcularDesglose->desglosar(new CalcularDesgloseComercialRequest(
            subtotalCentavos: $comando->subtotalCentavos,
            descuentoCentavos: $descuento?->descuentoCentavos ?? 0,
            moneda: $comando->moneda,
            financiamiento: $descuento?->financiamiento ?? FinanciamientoDescuento::porOmision(),
            categoriaAlojamiento: $comando->categoriaAlojamiento,
            nivelAnfitrion: $comando->nivelAnfitrion,
            reglaComisionNegociadaId: $acuerdo?->reglaComisionId,
            fecha: $fecha,
        ));

        return CotizacionResponse::desde(new Cotizacion(
            desglose: $desglose,
            promocionId: $descuento?->promocionId,
            acuerdoId: $acuerdo?->acuerdoId,
        ));
    }

    /**
     * Disparador: REST con cabecera `Idempotency-Key`.
     *
     * Crea el pago en estado PENDIENTE. Es el paso de la saga que MS-02 puede
     * reintentar, asi que es idempotente por la `Idempotency-Key`: si la clave ya
     * existe devuelve el pago que ya se creo, en lugar de crear otro. La columna
     * `idempotency_key` es UNIQUE, asi que la base es la ultima linea de defensa
     * si dos reintentos llegan a la vez.
     */
    public function crear(CrearPagoRequest $comando): PagoConTotalResponse
    {
        $yaExiste = $this->pagos->buscarPorIdempotencyKey($comando->idempotencyKey);

        if ($yaExiste !== null) {
            return PagoConTotalResponse::desde($yaExiste);
        }

        // Una reserva no puede tener dos pagos vivos a la vez; lo garantiza
        // tambien el indice unico parcial `uq_pago_vivo_por_reserva`.
        $vivo = $this->pagos->buscarVivoPorReserva($comando->reservaId);

        if ($vivo !== null) {
            throw new ConflictoDeEstado(
                "La reserva {$comando->reservaId} ya tiene un pago vivo ({$vivo->estado()->value})",
            );
        }

        $pago = Pago::crear(
            pagoId: $this->uuid->generar(),
            reservaId: $comando->reservaId,
            clienteId: $comando->clienteId,
            anfitrionId: $comando->anfitrionId,
            subtotal: Money::deCentavos($comando->subtotalCentavos, $comando->moneda),
            descuento: Money::deCentavos($comando->descuentoCentavos, $comando->moneda),
            cargoServicio: Money::deCentavos($comando->cargoServicioCentavos, $comando->moneda),
            metodo: $comando->metodo,
            idempotencyKey: $comando->idempotencyKey,
            sagaId: $comando->sagaId,
            promocionId: $comando->promocionId,
            pasarela: $comando->pasarela,
        );

        return PagoConTotalResponse::desde($this->tx->ejecutar(function () use ($pago): Pago {
            $this->pagos->guardar($pago);

            return $pago;
        }));
    }

    /**
     * Disparador: REST / INT.
     *
     * Es el paso de la saga donde MS-02 pide el cobro, y el caso de uso que el
     * documento de arquitectura usa como ejemplo. El orden importa:
     *
     *  1. la llamada a la pasarela va FUERA de la transaccion de base de datos,
     *     porque una llamada HTTP lenta no debe tener abierta una transaccion;
     *  2. el cambio de estado, la transaccion registrada y el evento van DENTRO de
     *     una sola transaccion (patron Outbox), asi nunca se publica
     *     `pago.capturado` de un pago que no quedo guardado.
     *
     * Si la pasarela rechaza, el pago queda FALLIDO y se emite `pago.fallido` para
     * que MS-02 compense y libere la disponibilidad.
     */
    public function cobrar(CobrarPagoRequest $comando): PagoConTotalResponse
    {
        $pago = $this->pagos->buscar($comando->pagoId)
            ?? throw new RecursoNoEncontrado("El pago {$comando->pagoId} no existe");

        // Un pago ya capturado no se vuelve a cobrar: el reintento es idempotente.
        if ($pago->estaCapturado()) {
            return PagoConTotalResponse::desde($pago);
        }

        // 1) Llamada externa FUERA de la transaccion de base de datos.
        $resultado = $this->pasarela->cobrar(
            $pago->pagoId(),
            $pago->total(),
            $comando->idempotencyKey ?? $pago->idempotencyKey(),
            $pago->metodo(),
        );

        // 2) Estado + transaccion + evento en UNA sola transaccion.
        return PagoConTotalResponse::desde($this->tx->ejecutar(function () use ($pago, $resultado): Pago {
            if ($resultado->exitoso) {
                $pago->capturar((string) $resultado->referencia, $this->pasarela->nombre());
            } else {
                $pago->marcarFallido($resultado->motivo);
            }

            $this->pagos->guardar($pago);

            $this->transacciones->agregar(TransaccionPago::registrar(
                transaccionId: $this->uuid->generar(),
                pagoId: $pago->pagoId(),
                tipo: TipoTransaccion::CAPTURA,
                monto: $pago->total(),
                estado: $resultado->exitoso ? EstadoTransaccion::EXITOSA : EstadoTransaccion::FALLIDA,
                referenciaPasarela: $resultado->referencia,
                respuestaPasarela: $resultado->respuesta,
            ));

            $this->eventos->publicar($pago->sacarEventos());

            return $pago;
        }));
    }

    /**
     * Disparador: REST / JOB.
     *
     * Dos usos: compensacion de la saga cuando falla otro paso, y expiracion de un
     * pago que quedo PENDIENTE demasiado tiempo (el JOB recorre
     * `PagoRepository::pendientesAnterioresA()`).
     *
     * Libera la reserva para el indice `uq_pago_vivo_por_reserva`, asi que despues
     * se puede crear otro pago para la misma reserva.
     */
    public function cancelarPendiente(CancelarPagoPendienteRequest $comando): PagoConTotalResponse
    {
        $pago = $this->pagos->buscar($comando->pagoId)
            ?? throw new RecursoNoEncontrado("El pago {$comando->pagoId} no existe");

        return PagoConTotalResponse::desde($this->tx->ejecutar(function () use ($pago, $comando): Pago {
            $pago->cancelar($comando->motivo);
            $this->pagos->guardar($pago);
            $this->eventos->publicar($pago->sacarEventos());

            return $pago;
        }));
    }

    /**
     * Disparador: webhook REST.
     *
     * La pasarela avisa por su cuenta, puede avisar tarde y puede avisar dos veces.
     * El filtro de repeticion es la `referencia_pasarela`, que es UNIQUE en
     * `transaccion_pago`: si ya existe una transaccion con esa referencia, el
     * webhook se descarta sin tocar nada.
     *
     * Tambien cubre el caso en que el cobro se confirma por webhook y no por la
     * respuesta sincrona de .
     */
    public function recibirNotificacionPasarela(RecibirNotificacionPasarelaRequest $comando): ResultadoNotificacion
    {
        if ($this->transacciones->buscarPorReferencia($comando->referenciaPasarela) !== null) {
            return ResultadoNotificacion::yaProcesada($comando->referenciaPasarela);
        }

        $pago = $this->pagos->buscar($comando->pagoId)
            ?? throw new RecursoNoEncontrado("El pago {$comando->pagoId} no existe");

        // Un pago ya cerrado no cambia de estado por un webhook atrasado, pero
        // la transaccion si se registra: el libro con la pasarela es append-only
        // y la conciliacion la necesita.
        $cambiaEstado = ! $pago->estaCapturado()
            && in_array($pago->estado()->value, ['PENDIENTE', 'AUTORIZADO'], true);

        return $this->tx->ejecutar(function () use ($comando, $pago, $cambiaEstado): ResultadoNotificacion {
            if ($cambiaEstado) {
                if ($comando->exitosa) {
                    $pago->capturar($comando->referenciaPasarela, $comando->pasarela);
                } else {
                    $pago->marcarFallido($comando->motivo);
                }

                $this->pagos->guardar($pago);
            }

            $this->transacciones->agregar(TransaccionPago::registrar(
                transaccionId: $this->uuid->generar(),
                pagoId: $pago->pagoId(),
                tipo: $comando->tipo ?? TipoTransaccion::CAPTURA,
                monto: $comando->montoNotificado ?? $pago->total(),
                estado: $comando->exitosa ? EstadoTransaccion::EXITOSA : EstadoTransaccion::FALLIDA,
                referenciaPasarela: $comando->referenciaPasarela,
                respuestaPasarela: $comando->payload,
            ));

            $this->eventos->publicar($pago->sacarEventos());

            return ResultadoNotificacion::aplicada($comando->referenciaPasarela, $cambiaEstado);
        });
    }

    /**
     * Disparador: INT por el evento `PagoCapturado`.
     *
     * Recibo o factura con numero unico. Es idempotente: si el pago ya tiene
     * comprobante se devuelve ese, porque el evento `pago.capturado` puede llegar
     * dos veces (entrega at-least-once) y la columna `numero` es UNIQUE.
     */
    public function emitirComprobante(EmitirComprobanteRequest $comando): ComprobanteResponse
    {
        $yaEmitido = $this->comprobantes->delPago($comando->pagoId);

        if ($yaEmitido !== null) {
            return ComprobanteResponse::desde($yaEmitido);
        }

        $pago = $this->pagos->buscar($comando->pagoId)
            ?? throw new RecursoNoEncontrado("El pago {$comando->pagoId} no existe");

        // Solo se factura lo que se cobro.
        if (! $pago->estaCapturado()) {
            throw new ConflictoDeEstado(
                "No se emite comprobante de un pago en estado {$pago->estado()->value}",
            );
        }

        $comprobante = Comprobante::emitir(
            comprobanteId: $this->uuid->generar(),
            pagoId: $pago->pagoId(),
            tipo: $comando->tipo,
            numero: $this->numerador->siguiente($comando->tipo),
            monto: $pago->total(),
            nitCliente: $comando->nitCliente,
            razonSocial: $comando->razonSocial,
        );

        return ComprobanteResponse::desde($this->tx->ejecutar(function () use ($comprobante): Comprobante {
            $this->comprobantes->guardar($comprobante);

            return $comprobante;
        }));
    }
}
