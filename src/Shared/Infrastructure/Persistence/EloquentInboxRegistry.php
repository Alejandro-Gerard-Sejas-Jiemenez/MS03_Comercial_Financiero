<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence;

use Src\Shared\Application\Port\InboxRegistry;

final class EloquentInboxRegistry implements InboxRegistry
{
    public function yaProcesado(string $mensajeId): bool
    {
        return EventoProcesadoModel::query()->whereKey($mensajeId)->exists();
    }

    public function marcar(string $mensajeId, string $tipoEvento, string $origen): void
    {
        // insertOrIgnore: si dos consumidores reciben el mismo mensaje a la vez,
        // la clave primaria decide y el segundo no revienta.
        EventoProcesadoModel::query()->insertOrIgnore([
            'mensaje_id' => $mensajeId,
            'tipo_evento' => $tipoEvento,
            'origen' => $origen,
        ]);
    }
}
