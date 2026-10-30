<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Enum;

enum TipoMovimiento: string
{
    case DEBITO = 'DEBITO';
    case CREDITO = 'CREDITO';
}
