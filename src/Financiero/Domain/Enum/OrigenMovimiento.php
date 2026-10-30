<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Enum;

enum OrigenMovimiento: string
{
    case PAGO = 'PAGO';
    case REEMBOLSO = 'REEMBOLSO';
    case COMISION = 'COMISION';
    case LIQUIDACION = 'LIQUIDACION';
}
