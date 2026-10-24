<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\Port;

use Src\Shared\Domain\Money;

/** Totales de un periodo para CU-LIQ-10. */
final class ResumenDeIngresos
{
    public function __construct(
        public readonly Money $totalCobrado,
        public readonly Money $totalComisiones,
        public readonly Money $totalCargosServicio,
        public readonly Money $totalReembolsos,
        public readonly Money $totalNetoAnfitriones,
        public readonly int $cantidadPagos,
    ) {
    }

    /** Lo que se queda la plataforma: comision mas cargo de servicio, menos reembolsos. */
    public function ingresoDeLaPlataforma(): Money
    {
        return $this->totalComisiones->mas($this->totalCargosServicio)->menos($this->totalReembolsos);
    }

    /** @return array<string, string|int> */
    public function aArreglo(): array
    {
        return [
            'total_cobrado' => $this->totalCobrado->aDecimal(),
            'total_comisiones' => $this->totalComisiones->aDecimal(),
            'total_cargos_servicio' => $this->totalCargosServicio->aDecimal(),
            'total_reembolsos' => $this->totalReembolsos->aDecimal(),
            'total_neto_anfitriones' => $this->totalNetoAnfitriones->aDecimal(),
            'ingreso_plataforma' => $this->ingresoDeLaPlataforma()->aDecimal(),
            'cantidad_pagos' => $this->cantidadPagos,
            'moneda' => $this->totalCobrado->moneda(),
        ];
    }
}
