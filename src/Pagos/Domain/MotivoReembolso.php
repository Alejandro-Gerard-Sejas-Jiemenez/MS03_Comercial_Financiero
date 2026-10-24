<?php

declare(strict_types=1);

namespace Src\Pagos\Domain;

enum MotivoReembolso: string
{
    case CANCELACION_CLIENTE = 'CANCELACION_CLIENTE';
    case CANCELACION_ANFITRION = 'CANCELACION_ANFITRION';
    case ERROR_COBRO = 'ERROR_COBRO';
    case DISPUTA = 'DISPUTA';
    case OTRO = 'OTRO';
}
