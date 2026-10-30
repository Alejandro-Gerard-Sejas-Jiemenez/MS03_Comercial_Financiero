<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\GestionarLiquidaciones;

final class AprobarLiquidacionRequest
{
    public function __construct(
        public readonly string $liquidacionId,
        public readonly string $aprobadoPor,
    ) {}
}
