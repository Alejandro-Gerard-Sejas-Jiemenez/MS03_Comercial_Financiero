<?php

declare(strict_types=1);

namespace Src\Monetizacion\Application\Port;

use Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial\CalcularDesgloseComercialRequest;
use Src\Monetizacion\Domain\ValueObject\DesgloseComercial;

/**
 * Puerto que consume Financiero para obtener el desglose como objeto de dominio
 * (no como Response): lo necesita para armar el pago y la comision.
 */
interface CalculadorDeDesglose
{
    public function desglosar(CalcularDesgloseComercialRequest $comando): DesgloseComercial;
}
