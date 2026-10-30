<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\RegistrarComision;

use Src\Acuerdos\Application\Port\AcuerdoComercialQuery;
use Src\Liquidaciones\Domain\Asiento;
use Src\Liquidaciones\Domain\Comision;
use Src\Liquidaciones\Domain\ComisionRepository;
use Src\Liquidaciones\Domain\LibroContableRepository;
use Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial\CalcularDesgloseComercialCommand;
use Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial\CalcularDesgloseComercialHandler;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Money;

/**
 * CU-LIQ-01 RegistrarComision (NEG, disparador INT por el evento `PagoCapturado`).
 *
 * Crea la comision en RETENIDA con la tarifa aplicada como snapshot y escribe
 * el asiento contable del cobro. Las dos cosas van en la misma transaccion: una
 * comision sin asiento, o al reves, dejaria el libro sin cuadrar.
 *
 * Es idempotente: `pago_id` es UNIQUE en `comision`, y el evento
 * `pago.capturado` puede llegar dos veces (entrega at-least-once), asi que si
 * ya hay comision de ese pago se devuelve esa.
 */
final class RegistrarComisionHandler
{
    public function __construct(
        private readonly ComisionRepository $comisiones,
        private readonly LibroContableRepository $libro,
        private readonly AcuerdoComercialQuery $acuerdos,
        private readonly CalcularDesgloseComercialHandler $calcularDesglose,
        private readonly UuidGenerator $uuid,
        private readonly TransactionManager $tx,
    ) {}

    public function __invoke(RegistrarComisionCommand $comando): Comision
    {
        $yaExiste = $this->comisiones->buscarPorPago($comando->pagoId);

        if ($yaExiste !== null) {
            return $yaExiste;
        }

        $acuerdo = $this->acuerdos->acuerdoActivoDe($comando->anfitrionId);

        // Se recalcula con las mismas reglas de CU-MON-02, no se confia en un
        // monto que venga de afuera.
        $desglose = ($this->calcularDesglose)(new CalcularDesgloseComercialCommand(
            subtotalCentavos: $comando->subtotalCentavos,
            descuentoCentavos: $comando->descuentoCentavos,
            moneda: $comando->moneda,
            financiamiento: $comando->financiamiento,
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

        return $this->tx->ejecutar(function () use ($comision, $asiento): Comision {
            $this->comisiones->guardar($comision);
            $this->libro->registrar($asiento);

            return $comision;
        });
    }
}
