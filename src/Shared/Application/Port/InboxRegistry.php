<?php

declare(strict_types=1);

namespace Src\Shared\Application\Port;

/**
 * Inbox (`comercial.evento_procesado`): consumidor idempotente. RabbitMQ
 * entrega al menos una vez, asi que el mensaje repetido debe descartarse.
 */
interface InboxRegistry
{
    public function yaProcesado(string $mensajeId): bool;

    public function marcar(string $mensajeId, string $tipoEvento, string $origen): void;
}
