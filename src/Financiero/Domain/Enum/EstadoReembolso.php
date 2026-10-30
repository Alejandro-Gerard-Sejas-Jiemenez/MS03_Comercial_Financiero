<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Enum;

enum EstadoReembolso: string
{
    case SOLICITADO = 'SOLICITADO';
    case APROBADO = 'APROBADO';
    case PROCESADO = 'PROCESADO';
    case RECHAZADO = 'RECHAZADO';
}
