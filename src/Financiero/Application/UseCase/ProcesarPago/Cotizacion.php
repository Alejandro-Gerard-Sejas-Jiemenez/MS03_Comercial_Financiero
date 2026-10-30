<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ProcesarPago;

use Src\Monetizacion\Domain\ValueObject\DesgloseComercial;

/**
 * Respuesta de . MS-02 la muestra al cliente y, si acepta, manda los
 * mismos montos a para que el total cobrado sea el total cotizado.
 */
final class Cotizacion
{
    public function __construct(
        public readonly DesgloseComercial $desglose,
        public readonly ?string $promocionId = null,
        public readonly ?string $acuerdoId = null,
    ) {}
}
