<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Enum;

enum EstadoPago: string
{
    case PENDIENTE = 'PENDIENTE';
    case AUTORIZADO = 'AUTORIZADO';
    case CAPTURADO = 'CAPTURADO';
    case FALLIDO = 'FALLIDO';
    case CANCELADO = 'CANCELADO';
    case REEMBOLSADO_PARCIAL = 'REEMBOLSADO_PARCIAL';
    case REEMBOLSADO = 'REEMBOLSADO';

    /** Estados que ocupan la reserva: el indice unico parcial solo deja uno de estos por reserva. */
    public function estaVivo(): bool
    {
        return in_array($this, [self::PENDIENTE, self::AUTORIZADO, self::CAPTURADO], true);
    }
}
