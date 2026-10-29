<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\MarcarComisionesLiquidables;

final class MarcarComisionesLiquidablesCommand
{
    public function __construct(
        public readonly string $reservaId,
        /** Si no viene, se toma del acuerdo ACTIVO del anfitrion. */
        public readonly ?int $diasLiquidacion = null,
    ) {}
}
