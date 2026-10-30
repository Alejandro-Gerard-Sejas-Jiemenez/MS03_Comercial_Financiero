<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Enum;

enum ResultadoConciliacionItem: string
{
    case COINCIDE = 'COINCIDE';
    case DIFERENCIA_MONTO = 'DIFERENCIA_MONTO';
    case NO_REGISTRADA = 'NO_REGISTRADA';
}
