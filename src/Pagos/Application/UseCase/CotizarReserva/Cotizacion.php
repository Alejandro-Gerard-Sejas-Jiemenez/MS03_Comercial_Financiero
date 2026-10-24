<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\CotizarReserva;

use Src\Monetizacion\Domain\DesgloseComercial;

/**
 * Respuesta de CU-PAG-01. MS-02 la muestra al cliente y, si acepta, manda los
 * mismos montos a CU-PAG-02 para que el total cobrado sea el total cotizado.
 */
final class Cotizacion
{
    public function __construct(
        public readonly DesgloseComercial $desglose,
        public readonly ?string $promocionId = null,
        public readonly ?string $acuerdoId = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function aArreglo(): array
    {
        return $this->desglose->aArreglo() + [
            'promocion_id' => $this->promocionId,
            'acuerdo_id' => $this->acuerdoId,
        ];
    }
}
