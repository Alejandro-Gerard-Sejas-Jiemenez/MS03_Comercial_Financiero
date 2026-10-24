<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\CrearPago;

use InvalidArgumentException;
use Src\Pagos\Domain\MetodoPago;

final class CrearPagoCommand
{
    public function __construct(
        public readonly string $reservaId,
        public readonly string $clienteId,
        public readonly string $anfitrionId,
        public readonly int $subtotalCentavos,
        public readonly int $cargoServicioCentavos,
        public readonly MetodoPago $metodo,
        /** Cabecera `Idempotency-Key`; en la saga de reserva es el `saga_id`. */
        public readonly string $idempotencyKey,
        public readonly int $descuentoCentavos = 0,
        public readonly string $moneda = 'BOB',
        public readonly ?string $sagaId = null,
        public readonly ?string $promocionId = null,
        public readonly ?string $pasarela = null,
    ) {
        if (trim($this->idempotencyKey) === '') {
            throw new InvalidArgumentException('Falta la Idempotency-Key');
        }
    }
}
