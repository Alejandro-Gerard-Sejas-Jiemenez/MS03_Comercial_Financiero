<?php

declare(strict_types=1);

namespace Src\Referencias\Application\UseCase\SincronizarReserva;

use Src\Referencias\Domain\EstadoReserva;
use Src\Referencias\Domain\ReservaReference;
use Src\Referencias\Domain\ReservaReferenceRepository;

/**
 * CU-REF-02 SincronizarReserva (EVT: `reserva.confirmada` / `reserva.actualizada`).
 *
 * Upsert en `ref_reserva`. Pagos y Liquidaciones leen de aqui las fechas y el
 * estado de la reserva sin llamar a MS-02.
 */
final class SincronizarReservaHandler
{
    public function __construct(private readonly ReservaReferenceRepository $reservas) {}

    public function __invoke(SincronizarReservaCommand $comando): ReservaReference
    {
        $reserva = new ReservaReference(
            reservaId: $comando->reservaId,
            clienteId: $comando->clienteId,
            anfitrionId: $comando->anfitrionId,
            alojamientoId: $comando->alojamientoId,
            fechaCheckin: $comando->fechaCheckin,
            fechaCheckout: $comando->fechaCheckout,
            estado: EstadoReserva::from($comando->estado),
            categoriaAlojamiento: $comando->categoriaAlojamiento,
        );

        $this->reservas->guardar($reserva);

        return $reserva;
    }
}
