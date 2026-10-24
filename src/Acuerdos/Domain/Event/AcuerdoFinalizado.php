<?php

declare(strict_types=1);

namespace Src\Acuerdos\Domain\Event;

use DateTimeImmutable;
use Src\Acuerdos\Domain\EstadoAcuerdo;
use Src\Shared\Domain\DomainEvent;

/**
 * `acuerdo_comercial.finalizado`. MS-01 lo consume para bloquear las
 * publicaciones del anfitrion cuando el acuerdo termina.
 *
 * Cubre las dos salidas terminales del acuerdo, porque para quien lo consume
 * el efecto es el mismo (el acuerdo dejo de regir): `estado_final` distingue
 * un cierre normal (`FINALIZADO`) de una rescision (`RESCINDIDO`).
 */
final class AcuerdoFinalizado implements DomainEvent
{
    public function __construct(
        private readonly string $acuerdoId,
        private readonly string $anfitrionId,
        private readonly EstadoAcuerdo $estadoFinal,
        private readonly ?string $motivo,
        private readonly ?DateTimeImmutable $fechaFin,
        private readonly ?string $sagaId = null,
    ) {
    }

    public function tipoEvento(): string
    {
        return 'acuerdo_comercial.finalizado';
    }

    public function agregadoTipo(): string
    {
        return 'acuerdo_comercial';
    }

    public function agregadoId(): string
    {
        return $this->acuerdoId;
    }

    public function payload(): array
    {
        return [
            'acuerdo_id' => $this->acuerdoId,
            'anfitrion_id' => $this->anfitrionId,
            'estado_final' => $this->estadoFinal->value,
            'motivo' => $this->motivo,
            'fecha_fin' => $this->fechaFin?->format('Y-m-d'),
        ];
    }

    public function sagaId(): ?string
    {
        return $this->sagaId;
    }
}
