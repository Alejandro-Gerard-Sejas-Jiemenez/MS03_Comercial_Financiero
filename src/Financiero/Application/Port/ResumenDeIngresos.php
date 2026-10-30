<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Port;

use Src\Shared\Domain\Money;

/** Totales de un periodo para . */
final class ResumenDeIngresos
{
    public function __construct(
        public readonly Money $totalCobrado,
        public readonly Money $totalComisiones,
        public readonly Money $totalCargosServicio,
        public readonly Money $totalReembolsos,
        public readonly Money $totalNetoAnfitriones,
        public readonly int $cantidadPagos,
    ) {}

    /** Lo que se queda la plataforma: comision mas cargo de servicio, menos reembolsos. */
    public function ingresoDeLaPlataforma(): Money
    {
        return $this->totalComisiones->mas($this->totalCargosServicio)->menos($this->totalReembolsos);
    }
}
