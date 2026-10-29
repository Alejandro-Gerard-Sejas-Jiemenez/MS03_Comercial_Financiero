<?php

declare(strict_types=1);

namespace Src\Shared\Presentation\Messaging;

/**
 * Lo que un paquete sabe hacer cuando llega un evento. Cada paquete traduce el
 * mensaje (JSON de RabbitMQ) al Command de su caso de uso; aqui no hay reglas.
 */
interface SuscriptorDeEventos
{
    /**
     * @return array<string, callable(array<string, mixed> $data, array<string, mixed> $mensaje): void> routing key => manejador
     */
    public function manejadores(): array;
}
