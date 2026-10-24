<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Evento de dominio. Es la estructura de datos simple que cruza la frontera
 * hacia el exterior: nunca viaja una entidad ni una fila de la base de datos.
 *
 * El nombre de `tipoEvento()` es la routing key de RabbitMQ ('pago.capturado').
 */
interface DomainEvent
{
    public function tipoEvento(): string;

    /** Tipo del agregado que lo emitio: 'pago', 'liquidacion', 'acuerdo_comercial'... */
    public function agregadoTipo(): string;

    public function agregadoId(): string;

    /** @return array<string, mixed> */
    public function payload(): array;

    /** Correlacion con la saga de reserva de MS-02, si la hay. */
    public function sagaId(): ?string;
}
