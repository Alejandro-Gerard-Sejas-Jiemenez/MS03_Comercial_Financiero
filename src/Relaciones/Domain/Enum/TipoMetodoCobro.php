<?php

declare(strict_types=1);

namespace Src\Relaciones\Domain\Enum;

/**
 * Tipos del CHECK `chk_metodo_tipo` de `metodo_cobro_anfitrion`.
 */
enum TipoMetodoCobro: string
{
    case CUENTA_BANCARIA = 'CUENTA_BANCARIA';
    case QR = 'QR';
    case BILLETERA = 'BILLETERA';
}
