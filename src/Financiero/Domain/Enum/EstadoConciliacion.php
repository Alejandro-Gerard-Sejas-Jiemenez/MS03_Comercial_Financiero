<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Enum;

enum EstadoConciliacion: string
{
    case ABIERTA = 'ABIERTA';
    case CONCILIADA = 'CONCILIADA';
    case CON_DIFERENCIAS = 'CON_DIFERENCIAS';
}
