<?php

declare(strict_types=1);

namespace Src\Monetizacion\Domain\Enum;

/**
 * Quien absorbe el descuento de una promocion. Decide la base comisionable:
 * si lo paga el anfitrion, la plataforma cobra comision sobre el monto ya
 * rebajado; si lo paga la plataforma, cobra sobre el subtotal completo.
 *
 * Vive en Monetizacion (no en Comercial) para que el calculo de la comision
 * no dependa del modulo de promociones.
 */
enum FinanciamientoDescuento: string
{
    case ANFITRION = 'ANFITRION';
    case PLATAFORMA = 'PLATAFORMA';

    /** Quien paga el descuento si nadie lo dice (el valor por omision de `promocion.financiada_por`). */
    public static function porOmision(): self
    {
        return self::ANFITRION;
    }
}
