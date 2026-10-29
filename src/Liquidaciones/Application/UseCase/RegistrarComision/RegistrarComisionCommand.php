<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\RegistrarComision;

use DateTimeImmutable;
use Src\Monetizacion\Domain\FinanciamientoDescuento;

/**
 * Se arma con el payload del evento `pago.capturado`.
 *
 * PENDIENTE DE INTEGRACION: `financiamiento` no viaja hoy en el payload de
 * `pago.capturado` (el pago solo guarda `promocion_id`). Por omision se asume
 * que el descuento lo financia el anfitrion, que es el valor por omision de la
 * columna `promocion.financiada_por`. Cuando exista el consumidor de la capa de
 * Presentacion, debe resolverlo con `promocion_id` y pasarlo aqui.
 */
final class RegistrarComisionCommand
{
    public function __construct(
        public readonly string $pagoId,
        public readonly string $reservaId,
        public readonly string $anfitrionId,
        public readonly int $subtotalCentavos,
        public readonly int $totalCentavos,
        public readonly int $descuentoCentavos = 0,
        public readonly string $moneda = 'BOB',
        public readonly FinanciamientoDescuento $financiamiento = FinanciamientoDescuento::ANFITRION,
        public readonly ?string $categoriaAlojamiento = null,
        public readonly ?string $nivelAnfitrion = null,
        public readonly ?DateTimeImmutable $fecha = null,
    ) {}
}
