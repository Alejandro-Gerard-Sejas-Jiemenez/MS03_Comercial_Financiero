<?php

declare(strict_types=1);

namespace Src\Acuerdos\Domain\Event;

use Src\Shared\Domain\DomainEvent;

/**
 * `acuerdo_comercial.suspendido`. MS-01 lo consume para bloquear las
 * publicaciones del anfitrion mientras el acuerdo este suspendido.
 */
final class AcuerdoSuspendido implements DomainEvent
{
    public function __construct(
        private readonly string $acuerdoId,
        private readonly string $anfitrionId,
        private readonly string $motivo,
        private readonly ?string $sagaId = null,
    ) {}

    public function tipoEvento(): string
    {
        return 'acuerdo_comercial.suspendido';
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
            'motivo' => $this->motivo,
        ];
    }

    public function sagaId(): ?string
    {
        return $this->sagaId;
    }
}
