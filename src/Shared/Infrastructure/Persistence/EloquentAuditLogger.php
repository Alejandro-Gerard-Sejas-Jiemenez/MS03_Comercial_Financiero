<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence;

use Src\Shared\Application\Port\AuditLogger;

final class EloquentAuditLogger implements AuditLogger
{
    public function registrar(
        string $accion,
        string $entidad,
        ?string $entidadId,
        ?array $antes = null,
        ?array $despues = null,
        ?string $usuarioId = null,
    ): void {
        AuditoriaLogModel::create([
            'usuario_id' => $usuarioId,
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'antes' => $antes,
            'despues' => $despues,
            // La IP la agregara el adaptador HTTP cuando exista la capa de Presentacion.
            'ip' => null,
        ]);
    }
}
