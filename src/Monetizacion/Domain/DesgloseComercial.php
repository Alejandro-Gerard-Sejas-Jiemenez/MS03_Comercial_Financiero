<?php

declare(strict_types=1);

namespace Src\Monetizacion\Domain;

use Src\Shared\Domain\Money;

/**
 * Resultado de CU-MON-02: lo que paga el cliente y lo que le queda al anfitrion.
 *
 * Estructura de datos simple, de las que si pueden cruzar las fronteras de
 * Clean Architecture.
 */
final class DesgloseComercial
{
    public function __construct(
        public readonly Money $subtotal,
        public readonly Money $descuento,
        public readonly Money $cargoServicio,
        public readonly Money $total,
        public readonly Money $baseComisionable,
        public readonly Money $montoComision,
        public readonly Money $netoAnfitrion,
        public readonly ?string $porcentajeAplicado = null,
        public readonly ?string $reglaComisionId = null,
        public readonly ?string $reglaCargoServicioId = null,
    ) {}

    /** @return array<string, string|null> listo para serializar o guardar */
    public function aArreglo(): array
    {
        return [
            'subtotal' => $this->subtotal->aDecimal(),
            'descuento' => $this->descuento->aDecimal(),
            'cargo_servicio' => $this->cargoServicio->aDecimal(),
            'monto_total' => $this->total->aDecimal(),
            'base_comisionable' => $this->baseComisionable->aDecimal(),
            'monto_comision' => $this->montoComision->aDecimal(),
            'monto_neto_anfitrion' => $this->netoAnfitrion->aDecimal(),
            'porcentaje_aplicado' => $this->porcentajeAplicado,
            'moneda' => $this->total->moneda(),
        ];
    }
}
