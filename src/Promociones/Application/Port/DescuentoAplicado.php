<?php

declare(strict_types=1);

namespace Src\Promociones\Application\Port;

use Src\Monetizacion\Domain\FinanciamientoDescuento;

/**
 * Descuento que `Pagos` debe restar del subtotal. `financiamiento` es el dato
 * que `Monetizacion` necesita para decidir la base comisionable.
 */
final class DescuentoAplicado
{
    public function __construct(
        public readonly string $promocionId,
        public readonly int $descuentoCentavos,
        public readonly string $moneda,
        public readonly FinanciamientoDescuento $financiamiento,
    ) {}
}
