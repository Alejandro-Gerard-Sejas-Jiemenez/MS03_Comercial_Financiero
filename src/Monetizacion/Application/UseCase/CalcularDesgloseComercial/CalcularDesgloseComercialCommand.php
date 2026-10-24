<?php

declare(strict_types=1);

namespace Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial;

use DateTimeImmutable;
use Src\Monetizacion\Domain\FinanciamientoDescuento;

final class CalcularDesgloseComercialCommand
{
    public function __construct(
        public readonly int $subtotalCentavos,
        public readonly int $descuentoCentavos = 0,
        public readonly string $moneda = 'BOB',
        public readonly FinanciamientoDescuento $financiamiento = FinanciamientoDescuento::ANFITRION,
        public readonly ?string $categoriaAlojamiento = null,
        public readonly ?string $nivelAnfitrion = null,
        /** Tarifa negociada en el acuerdo comercial; si viene, gana sobre la general. */
        public readonly ?string $reglaComisionNegociadaId = null,
        /** Fecha de aplicacion; por omision, hoy. */
        public readonly ?DateTimeImmutable $fecha = null,
    ) {
    }
}
