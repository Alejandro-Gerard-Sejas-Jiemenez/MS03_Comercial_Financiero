<?php

declare(strict_types=1);

namespace Src\Monetizacion\Domain\ValueObject;

use Src\Shared\Domain\Money;

/**
 * Resultado de lo que paga el cliente y lo que le queda al anfitrion.
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
}
