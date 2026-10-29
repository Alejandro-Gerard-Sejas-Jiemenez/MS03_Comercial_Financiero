<?php

declare(strict_types=1);

namespace Src\Acuerdos\Application\UseCase\GestionarAcuerdos;

use DateTimeImmutable;

/** Solo los campos no nulos se aplican. */
final class ActualizarCondicionesAcuerdoCommand
{
    /** @param array<string, mixed>|null $condiciones */
    public function __construct(
        public readonly string $acuerdoId,
        public readonly string $actualizadoPor,
        public readonly ?int $diasLiquidacion = null,
        public readonly ?string $reglaComisionId = null,
        public readonly ?array $condiciones = null,
        public readonly ?DateTimeImmutable $fechaFin = null,
    ) {}
}
