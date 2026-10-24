<?php

declare(strict_types=1);

namespace Src\Pagos\Domain;

enum TipoTransaccion: string
{
    case AUTORIZACION = 'AUTORIZACION';
    case CAPTURA = 'CAPTURA';
    case REEMBOLSO = 'REEMBOLSO';
    case CONTRACARGO = 'CONTRACARGO';
}
