<?php

declare(strict_types=1);

namespace Src\Monetizacion\Domain\Enum;

enum EstadoRegla: string
{
    case ACTIVA = 'ACTIVA';
    case INACTIVA = 'INACTIVA';
}
