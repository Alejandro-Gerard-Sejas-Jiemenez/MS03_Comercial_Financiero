<?php

declare(strict_types=1);

namespace Src\Promociones\Domain;

/** Tipos de `comercial.promocion` (CHECK `chk_promocion_tipo`). */
enum TipoPromocion: string
{
    case TEMPORADA = 'TEMPORADA';
    case FECHA_ESPECIAL = 'FECHA_ESPECIAL';
    case ESTADIA_PROLONGADA = 'ESTADIA_PROLONGADA';
    case CUPON = 'CUPON';
    case ULTIMO_MINUTO = 'ULTIMO_MINUTO';
}
