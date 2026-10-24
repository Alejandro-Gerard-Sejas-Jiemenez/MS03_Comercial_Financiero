<?php

declare(strict_types=1);

namespace Src\Shared\Application\Port;

/**
 * Broker de mensajeria (RabbitMQ, exchange topic `comercial.events`).
 * Solo lo usa el relay del Outbox (CU-SHA-01).
 */
interface MessageBroker
{
    /**
     * @param array<string, mixed> $mensaje
     * @throws \RuntimeException si el broker no esta disponible
     */
    public function enviar(string $routingKey, array $mensaje): void;
}
