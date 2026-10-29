<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\RevertirComision;

final class RevertirComisionCommand
{
    public function __construct(
        public readonly string $pagoId,
        /** Un reembolso parcial no borra la comision completa. */
        public readonly bool $esTotal = true,
    ) {}
}
