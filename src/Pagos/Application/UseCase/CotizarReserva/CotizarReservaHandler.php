<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\CotizarReserva;

use Src\Acuerdos\Application\Port\AcuerdoComercialQuery;
use Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial\CalcularDesgloseComercialCommand;
use Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial\CalcularDesgloseComercialHandler;
use Src\Monetizacion\Domain\FinanciamientoDescuento;
use Src\Promociones\Application\Port\CalculadorDeDescuento;
use Src\Promociones\Application\Port\SolicitudDescuento;
use Src\Shared\Application\Port\Clock;

/**
 * CU-PAG-01 CotizarReserva (NEG, REST desde MS-02).
 *
 * Primer paso de la saga de reserva: devuelve el desglose que vera el cliente
 * -- subtotal, descuento, cargo de servicio y total -- sin crear ningun pago ni
 * tocar nada. Es una operacion de solo lectura.
 *
 * No consulta `ref_reserva` a proposito: cuando MS-02 cotiza, la reserva
 * todavia no esta confirmada y el evento `reserva.confirmada` no llego, asi que
 * los datos del alojamiento y las fechas vienen en el comando.
 */
final class CotizarReservaHandler
{
    public function __construct(
        private readonly CalcularDesgloseComercialHandler $calcularDesglose,
        private readonly CalculadorDeDescuento $promociones,
        private readonly AcuerdoComercialQuery $acuerdos,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CotizarReservaCommand $comando): Cotizacion
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

        $desglose = ($this->calcularDesglose)(new CalcularDesgloseComercialCommand(
            subtotalCentavos: $comando->subtotalCentavos,
            descuentoCentavos: $descuento?->descuentoCentavos ?? 0,
            moneda: $comando->moneda,
            financiamiento: $descuento?->financiamiento ?? FinanciamientoDescuento::ANFITRION,
            categoriaAlojamiento: $comando->categoriaAlojamiento,
            nivelAnfitrion: $comando->nivelAnfitrion,
            reglaComisionNegociadaId: $acuerdo?->reglaComisionId,
            fecha: $fecha,
        ));

        return new Cotizacion(
            desglose: $desglose,
            promocionId: $descuento?->promocionId,
            acuerdoId: $acuerdo?->acuerdoId,
        );
    }
}
