<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Messaging;

use Illuminate\Support\Facades\Log;
use Src\Shared\Application\Port\MessageBroker;

/**
 * Broker de desarrollo: deja el mensaje en el log en vez de enviarlo a RabbitMQ.
 *
 * El adaptador real (php-amqplib sobre el exchange topic `comercial.events`)
 * implementa este mismo puerto y se cambia en el ServiceProvider con una sola
 * linea; ningun caso de uso se modifica.
 */
final class LogMessageBroker implements MessageBroker
{
    public function __construct(private readonly string $canal = 'stack')
    {
    }

    public function enviar(string $routingKey, array $mensaje): void
    {
        Log::channel($this->canal)->info("[outbox] {$routingKey}", $mensaje);
    }
}
