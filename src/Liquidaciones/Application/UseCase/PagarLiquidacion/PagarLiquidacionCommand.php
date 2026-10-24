<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\PagarLiquidacion;

use InvalidArgumentException;

final class PagarLiquidacionCommand
{
    public function __construct(
        public readonly string $liquidacionId,
        /** Comprobante de la transferencia bancaria o del QR. */
        public readonly string $referenciaTransferencia,
        public readonly string $pagadoPor,
    ) {
        if (trim($this->referenciaTransferencia) === '') {
            throw new InvalidArgumentException('Falta la referencia de la transferencia');
        }
    }
}
