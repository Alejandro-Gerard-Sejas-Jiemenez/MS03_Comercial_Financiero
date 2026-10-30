<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\GestionarComisiones;

use Src\Financiero\Application\Port\ComisionRepository;
use Src\Financiero\Application\Port\LibroContableRepository;
use Src\Financiero\Application\Response\ComisionResponse;
use Src\Financiero\Application\Response\ResultadoReversionResponse;
use Src\Financiero\Domain\Entity\Asiento;
use Src\Financiero\Domain\Entity\Comision;
use Src\Financiero\Domain\Enum\EstadoComision;
use Src\Monetizacion\Application\Port\CalculadorDeDesglose;
use Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial\CalcularDesgloseComercialRequest;
use Src\Monetizacion\Domain\Enum\FinanciamientoDescuento;
use Src\Relaciones\Application\Port\AcuerdoComercialQuery;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Money;

/** Caso de uso GestionarComisiones: implementa la interfaz de entrada GestionarComisionesUseCase. */
final class GestionarComisionesService implements GestionarComisionesUseCase
{
    public function __construct(
        private readonly ComisionRepository $comisiones,
        private readonly LibroContableRepository $libro,
        private readonly AcuerdoComercialQuery $acuerdos,
        private readonly CalculadorDeDesglose $calcularDesglose,
        private readonly UuidGenerator $uuid,
        private readonly TransactionManager $tx,
    ) {}

    /**
     * Disparador: INT por el evento `PagoCapturado`.
     *
     * Crea la comision en RETENIDA con la tarifa aplicada como snapshot y escribe
     * el asiento contable del cobro. Las dos cosas van en la misma transaccion: una
     * comision sin asiento, o al reves, dejaria el libro sin cuadrar.
     *
     * Es idempotente: `pago_id` es UNIQUE en `comision`, y el evento
     * `pago.capturado` puede llegar dos veces (entrega at-least-once), asi que si
     * ya hay comision de ese pago se devuelve esa.
     */
    public function registrar(RegistrarComisionRequest $comando): ComisionResponse
    {
        $yaExiste = $this->comisiones->buscarPorPago($comando->pagoId);

        if ($yaExiste !== null) {
            return ComisionResponse::desde($yaExiste);
        }

        $acuerdo = $this->acuerdos->acuerdoActivoDe($comando->anfitrionId);

        // Se recalcula con las mismas reglas de , no se confia en un
        // monto que venga de afuera.
        $desglose = $this->calcularDesglose->desglosar(new CalcularDesgloseComercialRequest(
            subtotalCentavos: $comando->subtotalCentavos,
            descuentoCentavos: $comando->descuentoCentavos,
            moneda: $comando->moneda,
            financiamiento: $comando->financiamiento ?? FinanciamientoDescuento::porOmision(),
            categoriaAlojamiento: $comando->categoriaAlojamiento,
            nivelAnfitrion: $comando->nivelAnfitrion,
            reglaComisionNegociadaId: $acuerdo?->reglaComisionId,
            fecha: $comando->fecha,
        ));

        $comision = Comision::registrar(
            comisionId: $this->uuid->generar(),
            pagoId: $comando->pagoId,
            reservaId: $comando->reservaId,
            anfitrionId: $comando->anfitrionId,
            baseComisionable: $desglose->baseComisionable,
            montoComision: $desglose->montoComision,
            porcentajeAplicado: $desglose->porcentajeAplicado,
            reglaId: $desglose->reglaComisionId,
            acuerdoId: $acuerdo?->acuerdoId,
        );

        // El cargo de servicio del asiento es el que el cliente PAGO (total - subtotal + descuento),
        // no el que hoy dicten las reglas: si la regla cambio o falta, el libro igual debe cuadrar
        // con el dinero que de verdad entro por la pasarela.
        $cargoCobrado = Money::deCentavos($comando->totalCentavos - $comando->subtotalCentavos + $comando->descuentoCentavos, $comando->moneda);

        $asiento = Asiento::porPagoCapturado(
            asientoId: $this->uuid->generar(),
            pagoId: $comando->pagoId,
            anfitrionId: $comando->anfitrionId,
            total: Money::deCentavos($comando->totalCentavos, $comando->moneda),
            comision: $desglose->montoComision,
            cargoServicio: $cargoCobrado,
            netoAnfitrion: $desglose->netoAnfitrion,
        );

        return ComisionResponse::desde($this->tx->ejecutar(function () use ($comision, $asiento): Comision {
            $this->comisiones->guardar($comision);
            $this->libro->registrar($asiento);

            return $comision;
        }));
    }

    /**
     * Disparador: INT por `reembolso.procesado`.
     *
     * Tres casos distintos, y ninguno reescribe el pasado:
     *
     *  1. la comision esta RETENIDA o LIQUIDABLE: se marca REVERTIDA y se escribe
     *     el asiento de reversa;
     *  2. la comision ya esta LIQUIDADA (al anfitrion ya se le pago): no se toca,
     *     se escribe el asiento de reversa y el monto queda para descontarse de la
     *     liquidacion siguiente;
     *  3. el reembolso fue parcial: la comision entera no corresponde revertirla,
     *     el ajuste va tambien en la liquidacion siguiente.
     *
     * En los casos 2 y 3 el resultado lo dice con
     * `requiereAjusteEnLaSiguienteLiquidacion`. Hoy ese descuento hay que pasarlo a
     * mano en `GenerarLiquidaciones`, porque el esquema tiene la columna
     * `total_reembolsos` pero no una tabla que diga que reembolso pertenece a que
     * liquidacion: es una decision pendiente, de la misma familia que la #4 del
     * documento de arquitectura.
     */
    public function revertir(RevertirComisionRequest $comando): ResultadoReversionResponse
    {
        $comision = $this->comisiones->buscarPorPago($comando->pagoId);

        if ($comision === null) {
            return ResultadoReversionResponse::desde(ResultadoReversion::sinComision($comando->pagoId));
        }

        if ($comision->estaRevertida()) {
            return ResultadoReversionResponse::desde(ResultadoReversion::revertida($comision));
        }

        $asiento = Asiento::porComisionRevertida(
            asientoId: $this->uuid->generar(),
            comisionId: $comision->comisionId(),
            anfitrionId: $comision->anfitrionId(),
            comision: $comision->montoComision(),
            netoAnfitrion: $comision->montoNetoAnfitrion(),
        );

        $yaLiquidada = $comision->estado() === EstadoComision::LIQUIDADA;
        $revierteEntera = $comando->esTotal && ! $yaLiquidada;

        return ResultadoReversionResponse::desde($this->tx->ejecutar(
            function () use ($comision, $asiento, $revierteEntera, $yaLiquidada): ResultadoReversion {
                if ($revierteEntera) {
                    $comision->revertir();
                    $this->comisiones->guardar($comision);
                }

                $this->libro->registrar($asiento);

                if ($revierteEntera) {
                    return ResultadoReversion::revertida($comision);
                }

                return $yaLiquidada
                    ? ResultadoReversion::yaLiquidada($comision)
                    : ResultadoReversion::parcial($comision);
            },
        ));
    }
}
