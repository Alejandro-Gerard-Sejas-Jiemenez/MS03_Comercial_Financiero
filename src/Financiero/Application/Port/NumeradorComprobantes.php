<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Port;

use Src\Financiero\Domain\Enum\TipoComprobante;

/**
 * Da el siguiente numero de comprobante. Es un puerto porque la numeracion
 * fiscal es un detalle externo (dosificacion, correlativo por sucursal) y
 * necesita un recurso compartido con exclusion mutua.
 */
interface NumeradorComprobantes
{
    public function siguiente(TipoComprobante $tipo): string;
}
