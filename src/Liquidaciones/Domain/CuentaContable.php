<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Domain;

/** Plan de cuentas de MS-03, igual que el CHECK de `movimiento_contable`. */
enum CuentaContable: string
{
    case PASARELA = 'PASARELA';
    case INGRESO_COMISION = 'INGRESO_COMISION';
    case INGRESO_CARGO_SERVICIO = 'INGRESO_CARGO_SERVICIO';
    case POR_PAGAR_ANFITRION = 'POR_PAGAR_ANFITRION';
    case REEMBOLSOS = 'REEMBOLSOS';
    case PROMOCIONES_PLATAFORMA = 'PROMOCIONES_PLATAFORMA';
}
