<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\RechazarLiquidacion;

use InvalidArgumentException;

final class RechazarLiquidacionCommand
{
    public function __construct(
        public readonly string $liquidacionId,
        public readonly string $revisadoPor,
        public readonly string $motivo,
        /** `true` anula en vez de rechazar; anular tambien sirve para una APROBADA. */
        public readonly bool $anular = false,
    ) {
        if (trim($this->motivo) === '') {
            throw new InvalidArgumentException('Rechazar o anular una liquidacion exige un motivo');
        }
    }
}
