<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Enum;

enum EstadoLiquidacion: string
{
    case GENERADA = 'GENERADA';
    case APROBADA = 'APROBADA';
    case PAGADA = 'PAGADA';
    case RECHAZADA = 'RECHAZADA';
    case ANULADA = 'ANULADA';
}
