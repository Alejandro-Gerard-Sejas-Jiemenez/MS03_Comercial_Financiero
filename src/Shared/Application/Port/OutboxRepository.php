<?php

declare(strict_types=1);

namespace Src\Shared\Application\Port;

use Src\Shared\Domain\DomainEvent;

/**
 * Tabla `comercial.outbox_evento`. La escritura ocurre dentro de la transaccion
 * del caso de uso; la publicacion a RabbitMQ la hace por separado.
 */
interface OutboxRepository
{
    public function agregar(DomainEvent $evento): void;

    /** @return list<EventoOutbox> eventos con `publicado_en IS NULL`, mas antiguos primero */
    public function pendientes(int $limite): array;

    public function marcarPublicado(string $eventoId): void;

    /** Suma 1 a `intentos` cuando la publicacion falla. */
    public function registrarIntento(string $eventoId): void;
}
