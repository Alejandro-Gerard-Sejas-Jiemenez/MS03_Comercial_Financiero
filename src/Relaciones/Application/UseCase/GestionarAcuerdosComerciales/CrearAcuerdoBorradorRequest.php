<?php

declare(strict_types=1);

namespace Src\Relaciones\Application\UseCase\GestionarAcuerdosComerciales;

use DateTimeImmutable;

final class CrearAcuerdoBorradorRequest
{
    /** @param array<string, mixed> $condiciones */
    public function __construct(
        public readonly string $anfitrionId,
        public readonly DateTimeImmutable $fechaInicio,
        public readonly string $creadoPor,
        public readonly int $diasLiquidacion = 7,
        public readonly ?string $reglaComisionId = null,
        public readonly ?DateTimeImmutable $fechaFin = null,
        public readonly array $condiciones = [],
    ) {}
}
