<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ProcesarPago;

use InvalidArgumentException;

final class CancelarPagoPendienteRequest
{
    public function __construct(
        public readonly string $pagoId,
        public readonly string $motivo,
    ) {
        if (trim($this->motivo) === '') {
            throw new InvalidArgumentException('Cancelar un pago exige un motivo');
        }
    }
}
