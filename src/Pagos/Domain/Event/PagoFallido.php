<?php

declare(strict_types=1);

namespace Src\Pagos\Domain\Event;

use Src\Shared\Domain\DomainEvent;

/**
 * `pago.fallido`. MS-02 compensa la saga: libera la disponibilidad que habia
 * bloqueado para esa reserva.
 */
final class PagoFallido implements DomainEvent
{
    public function __construct(
        private readonly string $pagoId,
        private readonly string $reservaId,
        private readonly ?string $motivo = null,
        private readonly ?string $sagaId = null,
    ) {
    }

    public function tipoEvento(): string
    {
        return 'pago.fallido';
    }

    public function agregadoTipo(): string
    {
        return 'pago';
    }

    public function agregadoId(): string
    {
        return $this->pagoId;
    }

    public function payload(): array
    {
        return [
            'pago_id' => $this->pagoId,
            'reserva_id' => $this->reservaId,
            'motivo' => $this->motivo,
        ];
    }

    public function sagaId(): ?string
    {
        return $this->sagaId;
    }
}
