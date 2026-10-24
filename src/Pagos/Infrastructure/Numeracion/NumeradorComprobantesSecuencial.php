<?php

declare(strict_types=1);

namespace Src\Pagos\Infrastructure\Numeracion;

use Src\Pagos\Application\Port\NumeradorComprobantes;
use Src\Pagos\Domain\TipoComprobante;
use Src\Pagos\Infrastructure\Persistence\ComprobanteModel;

/**
 * Correlativo por tipo y por ano: `RECIBO-2026-000123`.
 *
 * Lee el ultimo numero con `FOR UPDATE` para que dos peticiones simultaneas no
 * saquen el mismo correlativo; la columna `numero` es UNIQUE, asi que la base
 * es la ultima linea de defensa. Requiere estar dentro de una transaccion para
 * que el bloqueo valga: el caso de uso ya la abre.
 *
 * Un correlativo fiscal real (dosificacion, CUF, firma) lo dara el adaptador
 * del proveedor de facturacion, implementando esta misma interfaz.
 */
final class NumeradorComprobantesSecuencial implements NumeradorComprobantes
{
    public function siguiente(TipoComprobante $tipo): string
    {
        $prefijo = $tipo->value . '-' . date('Y') . '-';

        $ultimo = ComprobanteModel::query()
            ->where('numero', 'like', $prefijo . '%')
            ->orderByDesc('numero')
            ->lockForUpdate()
            ->value('numero');

        $correlativo = $ultimo === null
            ? 1
            : ((int) substr((string) $ultimo, strlen($prefijo))) + 1;

        return $prefijo . str_pad((string) $correlativo, 6, '0', STR_PAD_LEFT);
    }
}
