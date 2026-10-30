<?php

declare(strict_types=1);

namespace Src\Monetizacion\Domain\Enum;

enum TipoCalculo: string
{
    case PORCENTAJE = 'PORCENTAJE';
    case MONTO_FIJO = 'MONTO_FIJO';
}
