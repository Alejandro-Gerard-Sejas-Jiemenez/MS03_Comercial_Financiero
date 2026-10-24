<?php

declare(strict_types=1);

namespace Src\Monetizacion\Domain;

enum EstadoRegla: string
{
    case ACTIVA = 'ACTIVA';
    case INACTIVA = 'INACTIVA';
}
