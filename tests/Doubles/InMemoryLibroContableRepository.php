<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Liquidaciones\Domain\Asiento;
use Src\Liquidaciones\Domain\CuentaContable;
use Src\Liquidaciones\Domain\LibroContableRepository;
use Src\Liquidaciones\Domain\MovimientoContable;
use Src\Liquidaciones\Domain\TipoMovimiento;
use Src\Shared\Domain\Money;

final class InMemoryLibroContableRepository implements LibroContableRepository
{
    /** @var list<Asiento> */
    private array $asientos = [];

    public function registrar(Asiento $asiento): void
    {
        $this->asientos[] = $asiento;
    }

    public function saldoPorPagarDe(string $anfitrionId, string $moneda = 'BOB'): Money
    {
        $saldo = Money::cero($moneda);

        foreach ($this->movimientos() as $movimiento) {
            if ($movimiento->anfitrionId !== $anfitrionId) {
                continue;
            }

            if ($movimiento->cuenta !== CuentaContable::POR_PAGAR_ANFITRION) {
                continue;
            }

            $saldo = $movimiento->tipo === TipoMovimiento::CREDITO
                ? $saldo->mas($movimiento->monto)
                : $saldo->menos($movimiento->monto);
        }

        return $saldo;
    }

    /** @return list<Asiento> */
    public function asientos(): array
    {
        return $this->asientos;
    }

    /** @return list<MovimientoContable> */
    public function movimientos(): array
    {
        return array_merge(...array_map(
            static fn (Asiento $asiento): array => $asiento->movimientos(),
            $this->asientos,
        )) ?: [];
    }

    public function cantidadDeAsientos(): int
    {
        return count($this->asientos);
    }
}
