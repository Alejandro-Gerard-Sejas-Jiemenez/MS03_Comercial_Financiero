<?php

declare(strict_types=1);

namespace Src\Pagos\Domain;

enum EstadoConciliacion: string
{
    case ABIERTA = 'ABIERTA';
    case CONCILIADA = 'CONCILIADA';
    case CON_DIFERENCIAS = 'CON_DIFERENCIAS';
}
