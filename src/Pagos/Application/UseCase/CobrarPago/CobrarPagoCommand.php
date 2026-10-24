<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\CobrarPago;

final class CobrarPagoCommand
{
    public function __construct(
        public readonly string $pagoId,
        /** Si no viene, se usa la del propio pago. */
        public readonly ?string $idempotencyKey = null,
    ) {
    }
}
