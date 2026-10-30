<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\GestionarComisiones;

final class RevertirComisionRequest
{
    public function __construct(
        public readonly string $pagoId,
        /** Un reembolso parcial no borra la comision completa. */
        public readonly bool $esTotal = true,
    ) {}
}
