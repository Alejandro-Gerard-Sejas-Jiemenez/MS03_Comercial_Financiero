<?php

declare(strict_types=1);

namespace Src\Shared\Application\Port;

/**
 * Tabla `comercial.auditoria_log`. Servicio transversal, no es un caso de uso:
 * lo invocan los handlers que cambian datos sensibles.
 */
interface AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     */
    public function registrar(
        string $accion,
        string $entidad,
        ?string $entidadId,
        ?array $antes = null,
        ?array $despues = null,
        ?string $usuarioId = null,
    ): void;
}
