<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\Port;

use Src\Shared\Domain\Money;

final class IngresoPorCategoria
{
    public function __construct(
        /** Categoria de alojamiento de `ref_reserva`; null si la reserva no la trajo. */
        public readonly ?string $categoriaAlojamiento,
        public readonly Money $totalCobrado,
        public readonly Money $totalComisiones,
        public readonly int $cantidadPagos,
    ) {
    }
}
