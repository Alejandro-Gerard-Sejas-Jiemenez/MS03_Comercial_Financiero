<?php

declare(strict_types=1);

namespace Src\Shared\Application\UseCase\SincronizarReferencias;

use Src\Shared\Application\Port\AnfitrionReferenceRepository;
use Src\Shared\Application\Port\ReservaReferenceRepository;
use Src\Shared\Domain\Referencia\AnfitrionReference;
use Src\Shared\Domain\Referencia\EstadoReserva;
use Src\Shared\Domain\Referencia\EstadoVerificacionAnfitrion;
use Src\Shared\Domain\Referencia\ReservaReference;

/** Caso de uso SincronizarReferencias: implementa la interfaz de entrada SincronizarReferenciasUseCase. */
final class SincronizarReferenciasService implements SincronizarReferenciasUseCase
{
    public function __construct(
        private readonly AnfitrionReferenceRepository $anfitriones,
        private readonly ReservaReferenceRepository $reservas,
    ) {}

    /**
     * Disparador: evento `anfitrion.verificado` / `anfitrion.suspendido`.
     *
     * Upsert en `ref_anfitrion`. El consumidor de RabbitMQ que lo invoque debe
     * filtrar antes los mensajes repetidos con el inbox (`evento_procesado`), que
     * vive en `Shared`; lo hace `ConsumidorDeEventos` antes de llamar a este caso de uso.
     */
    public function sincronizarAnfitrion(SincronizarAnfitrionRequest $comando): void
    {
        $anfitrion = new AnfitrionReference(
            anfitrionId: $comando->anfitrionId,
            nombreCompleto: $comando->nombreCompleto,
            estadoVerificacion: EstadoVerificacionAnfitrion::from($comando->estado),
            nit: $comando->nit,
            razonSocial: $comando->razonSocial,
        );

        $this->anfitriones->guardar($anfitrion);
    }

    /**
     * Disparador: : `reserva.confirmada` / `reserva.actualizada`.
     *
     * Upsert en `ref_reserva`. Financiero lee de aqui las fechas y el
     * estado de la reserva sin llamar a MS-02.
     */
    public function sincronizarReserva(SincronizarReservaRequest $comando): void
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
    }
}
