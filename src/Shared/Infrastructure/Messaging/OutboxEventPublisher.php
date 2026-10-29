<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Messaging;

use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Domain\OutboxRepository;

/**
 * Implementacion del puerto de salida de eventos con el patron Outbox: no habla
 * con RabbitMQ, escribe la fila dentro de la transaccion del caso de uso. Si la
 * transaccion se revierte, el evento desaparece con ella; nunca queda un evento
 * publicado de un cambio que no se guardo.
 */
final class OutboxEventPublisher implements EventPublisher
{
    public function __construct(private readonly OutboxRepository $outbox) {}

    public function publicar(array $eventos): void
    {
        foreach ($eventos as $evento) {
            $this->outbox->agregar($evento);
        }
    }
}
