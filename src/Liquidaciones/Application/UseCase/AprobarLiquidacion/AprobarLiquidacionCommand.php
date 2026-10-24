<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\AprobarLiquidacion;

final class AprobarLiquidacionCommand
{
    public function __construct(
        public readonly string $liquidacionId,
        public readonly string $aprobadoPor,
    ) {
    }
}
