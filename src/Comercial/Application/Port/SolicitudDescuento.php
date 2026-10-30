<?php

declare(strict_types=1);

namespace Src\Comercial\Application\Port;

use DateTimeImmutable;

/**
 * Lo que Financiero (CotizarReserva, CrearPago) le pregunta a
 * Comercial. Estructura de datos simple: por la frontera no viaja la
 * entidad ni el modelo de Eloquent.
 */
final class SolicitudDescuento
{
    public function __construct(
        public readonly int $subtotalCentavos,
        public readonly int $noches,
        public readonly string $moneda = 'BOB',
        public readonly ?string $codigoCupon = null,
        public readonly ?string $promocionId = null,
        public readonly ?string $alojamientoId = null,
        public readonly ?string $anfitrionId = null,
        public readonly ?DateTimeImmutable $fecha = null,
    ) {}
}
