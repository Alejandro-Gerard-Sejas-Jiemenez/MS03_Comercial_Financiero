<?php

declare(strict_types=1);

namespace Src\Pagos\Domain;

enum ResultadoConciliacionItem: string
{
    case COINCIDE = 'COINCIDE';
    case DIFERENCIA_MONTO = 'DIFERENCIA_MONTO';
    case NO_REGISTRADA = 'NO_REGISTRADA';
}
