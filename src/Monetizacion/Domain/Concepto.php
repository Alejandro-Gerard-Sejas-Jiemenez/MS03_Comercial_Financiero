<?php

declare(strict_types=1);

namespace Src\Monetizacion\Domain;

/** Que cobra la regla: la comision al anfitrion o el cargo de servicio al cliente. */
enum Concepto: string
{
    case COMISION_ANFITRION = 'COMISION_ANFITRION';
    case CARGO_SERVICIO_CLIENTE = 'CARGO_SERVICIO_CLIENTE';
}
