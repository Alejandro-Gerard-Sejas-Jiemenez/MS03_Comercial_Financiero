<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Referencia;

/** Estado de la reserva segun MS-02, copiado por eventos. */
enum EstadoReserva: string
{
    case SOLICITADA = 'SOLICITADA';
    case CONFIRMADA = 'CONFIRMADA';
    case EN_CURSO = 'EN_CURSO';
    case COMPLETADA = 'COMPLETADA';
    case CANCELADA = 'CANCELADA';
    case NO_SHOW = 'NO_SHOW';
}
