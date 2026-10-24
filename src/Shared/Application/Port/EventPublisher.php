<?php

declare(strict_types=1);

namespace Src\Shared\Application\Port;

use Src\Shared\Domain\DomainEvent;

/**
 * Salida de eventos del microservicio. La implementacion de produccion escribe
 * en `outbox_evento` dentro de la transaccion del caso de uso; en las pruebas
 * se reemplaza por un doble en memoria.
 */
interface EventPublisher
{
    /** @param list<DomainEvent> $eventos */
    public function publicar(array $eventos): void;
}
