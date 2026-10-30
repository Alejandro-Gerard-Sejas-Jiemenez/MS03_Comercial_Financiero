<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Enum;

enum TipoComprobante: string
{
    case RECIBO = 'RECIBO';
    case FACTURA = 'FACTURA';
    case NOTA_CREDITO = 'NOTA_CREDITO';
}
