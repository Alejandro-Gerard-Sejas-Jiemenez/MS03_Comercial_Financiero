<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Domain;

enum TipoMovimiento: string
{
    case DEBITO = 'DEBITO';
    case CREDITO = 'CREDITO';
}
