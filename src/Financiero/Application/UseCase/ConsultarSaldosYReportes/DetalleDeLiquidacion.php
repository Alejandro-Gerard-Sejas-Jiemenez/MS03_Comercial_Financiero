<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ConsultarSaldosYReportes;

use Src\Financiero\Domain\Entity\Comision;
use Src\Financiero\Domain\Entity\Liquidacion;

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
