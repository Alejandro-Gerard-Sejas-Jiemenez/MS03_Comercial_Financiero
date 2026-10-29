<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Fila pendiente de `comercial.outbox_evento` vista desde el dominio.
 */
final class EventoOutbox
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly string $eventoId,
        public readonly string $agregadoTipo,
        public readonly string $agregadoId,
        public readonly string $tipoEvento,
        public readonly array $payload,
        public readonly ?string $sagaId,
        public readonly int $intentos,
    ) {}
}
