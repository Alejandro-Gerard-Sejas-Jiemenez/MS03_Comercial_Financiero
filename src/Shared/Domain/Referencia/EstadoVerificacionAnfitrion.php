<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Referencia;

/** Estado del anfitrion segun MS-01, copiado por eventos. */
enum EstadoVerificacionAnfitrion: string
{
    case PENDIENTE = 'PENDIENTE';
    case VERIFICADO = 'VERIFICADO';
    case SUSPENDIDO = 'SUSPENDIDO';
    case BLOQUEADO = 'BLOQUEADO';
}
