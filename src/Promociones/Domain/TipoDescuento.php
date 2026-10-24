<?php

declare(strict_types=1);

namespace Src\Promociones\Domain;

/**
 * Como se lee la columna `valor` de `comercial.promocion`
 * (CHECK `chk_promocion_tipo_descuento`): PORCENTAJE la lee como un porcentaje
 * con dos decimales sobre el subtotal; MONTO_FIJO la lee como un monto.
 */
enum TipoDescuento: string
{
    case PORCENTAJE = 'PORCENTAJE';
    case MONTO_FIJO = 'MONTO_FIJO';
}
