<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\ProcesarReembolso;

final class ProcesarReembolsoCommand
{
    public function __construct(
        public readonly string $reembolsoId,
        /** Si no viene, se arma como `reembolso-<id>`: el proveedor no devuelve dos veces. */
        public readonly ?string $idempotencyKey = null,
    ) {
    }
}
