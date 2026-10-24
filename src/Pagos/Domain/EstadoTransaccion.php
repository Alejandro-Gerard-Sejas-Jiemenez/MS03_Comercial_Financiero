<?php

declare(strict_types=1);

namespace Src\Pagos\Domain;

enum EstadoTransaccion: string
{
    case EXITOSA = 'EXITOSA';
    case FALLIDA = 'FALLIDA';
    case PENDIENTE = 'PENDIENTE';
}
