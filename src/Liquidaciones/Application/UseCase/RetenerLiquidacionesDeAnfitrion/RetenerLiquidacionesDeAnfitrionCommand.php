<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\RetenerLiquidacionesDeAnfitrion;

final class RetenerLiquidacionesDeAnfitrionCommand
{
    public function __construct(
        public readonly string $anfitrionId,
        public readonly string $motivo = 'anfitrion suspendido en MS-01',
    ) {
    }
}
