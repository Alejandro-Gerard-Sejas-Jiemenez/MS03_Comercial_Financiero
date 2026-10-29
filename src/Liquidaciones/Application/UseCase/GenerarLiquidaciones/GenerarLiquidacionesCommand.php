<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\GenerarLiquidaciones;

use DateTimeImmutable;

final class GenerarLiquidacionesCommand
{
    /**
     * @param  array<string, int>  $ajustesPorReembolso  anfitrion_id => centavos a descontar.
     *                                                   Hoy se pasa a mano: ver la nota de CU-LIQ-02 sobre `total_reembolsos`.
     */
    public function __construct(
        public readonly ?DateTimeImmutable $hasta = null,
        public readonly ?DateTimeImmutable $periodoDesde = null,
        public readonly string $moneda = 'BOB',
        public readonly array $ajustesPorReembolso = [],
    ) {}
}
