<?php

declare(strict_types=1);

namespace Src\Promociones\Domain;

/**
 * Estados de `comercial.promocion` (CHECK `chk_promocion_estado`).
 *
 * BORRADOR se activa; ACTIVA se pausa o se cancela; VENCIDA la pone el JOB
 * CU-PRO-04 cuando pasa `vigente_hasta`. VENCIDA y CANCELADA son terminales.
 */
enum EstadoPromocion: string
{
    case BORRADOR = 'BORRADOR';
    case ACTIVA = 'ACTIVA';
    case PAUSADA = 'PAUSADA';
    case VENCIDA = 'VENCIDA';
    case CANCELADA = 'CANCELADA';

    public function esTerminal(): bool
    {
        return $this === self::VENCIDA || $this === self::CANCELADA;
    }
}
