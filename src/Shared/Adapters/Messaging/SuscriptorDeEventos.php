<?php

declare(strict_types=1);

namespace Src\Shared\Adapters\Messaging;

/**
 * Lo que un modulo sabe hacer cuando llega un evento. Cada modulo traduce el
 * mensaje (JSON de RabbitMQ) al Request de su caso de uso; aqui no hay reglas.
 */
interface SuscriptorDeEventos
{
    /**
     * @return array<string, callable(array<string, mixed> $data, array<string, mixed> $mensaje): void> routing key => manejador
     */
    public function manejadores(): array;
}
