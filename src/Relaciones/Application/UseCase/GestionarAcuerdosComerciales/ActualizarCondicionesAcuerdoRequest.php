<?php

declare(strict_types=1);

namespace Src\Relaciones\Application\UseCase\GestionarAcuerdosComerciales;

use DateTimeImmutable;

/** Solo los campos no nulos se aplican. */
final class ActualizarCondicionesAcuerdoRequest
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
