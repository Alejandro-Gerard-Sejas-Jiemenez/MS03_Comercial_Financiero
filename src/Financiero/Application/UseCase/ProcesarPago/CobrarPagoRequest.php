<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ProcesarPago;

final class CobrarPagoRequest
{
    public function __construct(
        public readonly string $pagoId,
        /** Si no viene, se usa la del propio pago. */
        public readonly ?string $idempotencyKey = null,
    ) {}
}
