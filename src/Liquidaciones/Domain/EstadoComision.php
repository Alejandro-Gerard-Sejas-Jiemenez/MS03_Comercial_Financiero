<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Domain;

/**
 * Ciclo de una comision: se retiene al cobrar, se vuelve liquidable cuando la
 * estadia termina y su plazo vence, se liquida al generar la liquidacion, o se
 * revierte si hubo reembolso.
 */
enum EstadoComision: string
{
    case RETENIDA = 'RETENIDA';
    case LIQUIDABLE = 'LIQUIDABLE';
    case LIQUIDADA = 'LIQUIDADA';
    case REVERTIDA = 'REVERTIDA';
}
