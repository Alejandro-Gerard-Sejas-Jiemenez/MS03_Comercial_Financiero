<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\GestionarLiquidaciones;

final class RetenerLiquidacionesDeAnfitrionRequest
{
    public function __construct(
        public readonly string $anfitrionId,
        public readonly string $motivo = 'anfitrion suspendido en MS-01',
    ) {}
}
