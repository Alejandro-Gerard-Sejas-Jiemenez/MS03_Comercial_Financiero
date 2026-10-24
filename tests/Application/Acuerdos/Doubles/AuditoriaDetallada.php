<?php

declare(strict_types=1);

namespace Tests\Application\Acuerdos\Doubles;

use Src\Shared\Application\Port\AuditLogger;

/**
 * Igual que `Tests\Doubles\SpyAuditLogger` pero guarda tambien `antes` y
 * `despues`, porque estas pruebas necesitan afirmar que el numero de cobro
 * nunca llega al log de auditoria.
 */
final class AuditoriaDetallada implements AuditLogger
{
    /** @var list<array<string, mixed>> */
    private array $registros = [];

    public function registrar(
        string $accion,
        string $entidad,
        ?string $entidadId,
        ?array $antes = null,
        ?array $despues = null,
        ?string $usuarioId = null,
    ): void {
        $this->registros[] = [
            'accion' => $accion,
            'entidad' => $entidad,
            'entidadId' => $entidadId,
            'antes' => $antes,
            'despues' => $despues,
            'usuarioId' => $usuarioId,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function registros(): array
    {
        return $this->registros;
    }

    /** @return list<string> */
    public function acciones(): array
    {
        return array_map(static fn (array $r): string => (string) $r['accion'], $this->registros);
    }

    public function volcado(): string
    {
        return print_r($this->registros, true);
    }
}
