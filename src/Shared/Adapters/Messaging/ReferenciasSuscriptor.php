<?php

declare(strict_types=1);

namespace Src\Shared\Adapters\Messaging;

use DateTimeImmutable;
use Src\Shared\Application\UseCase\SincronizarReferencias\SincronizarAnfitrionRequest;
use Src\Shared\Application\UseCase\SincronizarReferencias\SincronizarReferenciasUseCase;
use Src\Shared\Application\UseCase\SincronizarReferencias\SincronizarReservaRequest;

/**
 * copias locales de MS-01 (anfitriones) y MS-02 (reservas).
 *
 * Forma de `data` que se espera (propuesta a acordar con MS-01 y MS-02):
 *  - anfitrion.*: anfitrion_id, nombre_completo, nit?, razon_social?
 *  - reserva.*:  reserva_id, cliente_id, anfitrion_id, alojamiento_id,
 *                fecha_checkin, fecha_checkout, estado, categoria_alojamiento?
 */
final class ReferenciasSuscriptor implements SuscriptorDeEventos
{
    public function __construct(
        private readonly SincronizarReferenciasUseCase $sincronizarReferencias,
    ) {}

    public function manejadores(): array
    {
        $anfitrion = fn (string $estado): callable => function (array $d) use ($estado): void {
            $this->sincronizarReferencias->sincronizarAnfitrion(new SincronizarAnfitrionRequest(
                anfitrionId: (string) $d['anfitrion_id'],
                nombreCompleto: (string) $d['nombre_completo'],
                estado: $estado,
                nit: $d['nit'] ?? null,
                razonSocial: $d['razon_social'] ?? null,
            ));
        };

        $reserva = function (array $d): void {
            // Un aviso de cancelacion puede venir sin fechas: ahi no hay nada que copiar.
            if (! isset($d['fecha_checkin'], $d['fecha_checkout'], $d['alojamiento_id'], $d['cliente_id'])) {
                return;
            }
            $this->sincronizarReferencias->sincronizarReserva(new SincronizarReservaRequest(
                reservaId: (string) $d['reserva_id'],
                clienteId: (string) $d['cliente_id'],
                anfitrionId: (string) $d['anfitrion_id'],
                alojamientoId: (string) $d['alojamiento_id'],
                fechaCheckin: new DateTimeImmutable((string) $d['fecha_checkin']),
                fechaCheckout: new DateTimeImmutable((string) $d['fecha_checkout']),
                estado: (string) $d['estado'],
                categoriaAlojamiento: $d['categoria_alojamiento'] ?? null,
            ));
        };

        return [
            'anfitrion.verificado' => $anfitrion('VERIFICADO'),
            'anfitrion.suspendido' => $anfitrion('SUSPENDIDO'),
            'reserva.confirmada' => $reserva,
            'reserva.actualizada' => $reserva,
            // Se sincroniza antes de que Financiero calcule el reembolso con sus fechas.
            'reserva.cancelada' => $reserva,
        ];
    }
}
