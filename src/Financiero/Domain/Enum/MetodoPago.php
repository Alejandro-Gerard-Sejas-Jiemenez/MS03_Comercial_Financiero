<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Enum;

enum MetodoPago: string
{
    case QR = 'QR';
    case TARJETA = 'TARJETA';
    case TRANSFERENCIA = 'TRANSFERENCIA';
    case BILLETERA = 'BILLETERA';
}
