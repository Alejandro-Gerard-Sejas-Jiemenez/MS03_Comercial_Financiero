<?php

declare(strict_types=1);

namespace Src\Pagos\Domain;

enum MetodoPago: string
{
    case QR = 'QR';
    case TARJETA = 'TARJETA';
    case TRANSFERENCIA = 'TRANSFERENCIA';
    case BILLETERA = 'BILLETERA';
}
