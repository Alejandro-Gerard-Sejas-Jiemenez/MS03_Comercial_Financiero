<?php

declare(strict_types=1);

namespace Src\Acuerdos\Application\Port;

/**
 * Estructura de datos simple que cruza la frontera hacia Liquidaciones y
 * Pagos: lo que esos paquetes necesitan del acuerdo, sin la entidad ni el
 * modelo de Eloquent.
 */
final class AcuerdoVigente
{
    public function __construct(
        public readonly string $acuerdoId,
        public readonly string $anfitrionId,
        public readonly int $diasLiquidacion,
        public readonly ?string $reglaComisionId,
    ) {
    }
}
