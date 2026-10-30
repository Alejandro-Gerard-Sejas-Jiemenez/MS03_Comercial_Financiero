<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Enum;

enum TipoTransaccion: string
{
    case AUTORIZACION = 'AUTORIZACION';
    case CAPTURA = 'CAPTURA';
    case REEMBOLSO = 'REEMBOLSO';
    case CONTRACARGO = 'CONTRACARGO';
}
