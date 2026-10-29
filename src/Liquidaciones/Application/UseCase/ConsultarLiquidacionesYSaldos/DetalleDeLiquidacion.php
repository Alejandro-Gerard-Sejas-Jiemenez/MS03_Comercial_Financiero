<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\ConsultarLiquidacionesYSaldos;

use Src\Liquidaciones\Domain\Comision;
use Src\Liquidaciones\Domain\Liquidacion;

final class DetalleDeLiquidacion
{
    /** @param list<Comision> $comisiones */
    public function __construct(
        public readonly Liquidacion $liquidacion,
        public readonly array $comisiones,
    ) {}

    public function cantidadDeReservas(): int
    {
        return count($this->comisiones);
    }
}
