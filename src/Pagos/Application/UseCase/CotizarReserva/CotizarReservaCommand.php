<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\CotizarReserva;

use DateTimeImmutable;
use InvalidArgumentException;

final class CotizarReservaCommand
{
    public function __construct(
        public readonly string $anfitrionId,
        public readonly int $subtotalCentavos,
        public readonly int $noches,
        public readonly ?string $alojamientoId = null,
        public readonly ?string $categoriaAlojamiento = null,
        public readonly ?string $nivelAnfitrion = null,
        public readonly ?string $codigoCupon = null,
        public readonly ?string $promocionId = null,
        public readonly string $moneda = 'BOB',
        public readonly ?DateTimeImmutable $fecha = null,
    ) {
        if ($this->subtotalCentavos < 0) {
            throw new InvalidArgumentException('El subtotal no puede ser negativo');
        }

        if ($this->noches < 1) {
            throw new InvalidArgumentException('Una reserva tiene al menos una noche');
        }
    }
}
