<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\CancelarPagoPendiente;

use InvalidArgumentException;

final class CancelarPagoPendienteCommand
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
