<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Entity;

use Src\Financiero\Domain\Enum\CuentaContable;
use Src\Financiero\Domain\Enum\OrigenMovimiento;
use Src\Financiero\Domain\Enum\TipoMovimiento;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;

/**
 * Una linea del libro contable. Inmutable, y append-only en la base de datos:
 * una correccion se hace con un asiento de reversa, nunca editando este.
 */
final class MovimientoContable
{
    public function __construct(
        public readonly string $asientoId,
        public readonly CuentaContable $cuenta,
        public readonly TipoMovimiento $tipo,
        public readonly Money $monto,
        public readonly OrigenMovimiento $origenTipo,
        public readonly string $origenId,
        public readonly ?string $anfitrionId = null,
    ) {
        if (! $this->monto->esPositivo()) {
            throw new ReglaDeNegocioViolada(
                'Un movimiento contable tiene monto positivo; el signo lo da DEBITO o CREDITO',
            );
        }
    }
}
