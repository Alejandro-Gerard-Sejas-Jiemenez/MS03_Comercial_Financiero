<?php

declare(strict_types=1);

namespace Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial;

use Src\Monetizacion\Application\Response\DesgloseComercialResponse;

/**
 * Entrada del caso de uso CalcularDesgloseComercial (Clean Architecture: Input Boundary).
 * Los adaptadores (controladores, consolas, suscriptores) dependen de esta interfaz, no del servicio.
 */
interface CalcularDesgloseComercialUseCase
{
    public function calcular(CalcularDesgloseComercialRequest $comando): DesgloseComercialResponse;
}
