<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Shared\Application\Port\InboxRegistry;

final class InMemoryInboxRegistry implements InboxRegistry
{
    /** @var array<string, array{tipo: string, origen: string}> */
    private array $procesados = [];

    public function yaProcesado(string $mensajeId): bool
    {
        return isset($this->procesados[$mensajeId]);
    }

    public function marcar(string $mensajeId, string $tipoEvento, string $origen): void
    {
        $this->procesados[$mensajeId] = ['tipo' => $tipoEvento, 'origen' => $origen];
    }
}
