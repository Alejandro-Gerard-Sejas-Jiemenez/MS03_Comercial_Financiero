<?php

declare(strict_types=1);

namespace Src\Promociones\Application\UseCase\RegistrarUsoDePromocion;

use DateTimeImmutable;

/**
 * Lo arma el consumidor del evento `pago.capturado` (`PagoCapturado`) con el
 * descuento que ya se cobro. `aplicadaEn` es opcional: por omision la pone el
 * puerto Clock.
 */
final class RegistrarUsoDePromocionCommand
{
    public function __construct(
        public readonly string $promocionId,
        public readonly int $descuentoCentavos,
        public readonly string $moneda = 'BOB',
        public readonly ?string $reservaId = null,
        public readonly ?string $pagoId = null,
        public readonly ?string $sagaId = null,
        public readonly ?DateTimeImmutable $aplicadaEn = null,
    ) {}
}
