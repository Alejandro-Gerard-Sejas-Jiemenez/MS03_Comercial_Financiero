<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Shared\Application\Port\AuditLogger;

final class SpyAuditLogger implements AuditLogger
{
    /** @var list<array{accion: string, entidad: string, entidadId: string|null}> */
    private array $registros = [];

    public function registrar(
        string $accion,
        string $entidad,
        ?string $entidadId,
        ?array $antes = null,
        ?array $despues = null,
        ?string $usuarioId = null,
    ): void {
        $this->registros[] = ['accion' => $accion, 'entidad' => $entidad, 'entidadId' => $entidadId];
    }

    /** @return list<array{accion: string, entidad: string, entidadId: string|null}> */
    public function registros(): array
    {
        return $this->registros;
    }
}
