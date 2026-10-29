<?php

declare(strict_types=1);

namespace Src\Pagos\Presentation\Messaging;

use DateTimeImmutable;
use Src\Pagos\Application\UseCase\SolicitarReembolso\SolicitarReembolsoCommand;
use Src\Pagos\Application\UseCase\SolicitarReembolso\SolicitarReembolsoHandler;
use Src\Pagos\Domain\MotivoReembolso;
use Src\Shared\Presentation\Messaging\SuscriptorDeEventos;

/**
 * CU-PAG-07: una reserva cancelada abre la solicitud de reembolso (queda
 * SOLICITADO; un ADMIN la aprueba). `data`: reserva_id, cancelada_por
 * (`ANFITRION` | `CLIENTE`, por omision CLIENTE), fecha_cancelacion?.
 */
final class PagosSuscriptor implements SuscriptorDeEventos
{
    public function __construct(private readonly SolicitarReembolsoHandler $solicitar) {}

    public function manejadores(): array
    {
        return [
            'reserva.cancelada' => function (array $d): void {
                ($this->solicitar)(new SolicitarReembolsoCommand(
                    reservaId: (string) $d['reserva_id'],
                    motivo: ($d['cancelada_por'] ?? 'CLIENTE') === 'ANFITRION'
                        ? MotivoReembolso::CANCELACION_ANFITRION
                        : MotivoReembolso::CANCELACION_CLIENTE,
                    fechaCancelacion: isset($d['fecha_cancelacion']) ? new DateTimeImmutable((string) $d['fecha_cancelacion']) : null,
                    solicitadoPor: null,
                ));
            },
        ];
    }
}
