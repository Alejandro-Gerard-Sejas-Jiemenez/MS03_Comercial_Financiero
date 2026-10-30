<?php

declare(strict_types=1);

namespace Src\Financiero\Infrastructure\Persistence\Repository;

use Src\Financiero\Application\Port\LibroContableRepository;
use Src\Financiero\Domain\Entity\Asiento;
use Src\Financiero\Domain\Entity\MovimientoContable;
use Src\Financiero\Domain\Enum\CuentaContable;
use Src\Financiero\Domain\Enum\TipoMovimiento;
use Src\Financiero\Infrastructure\Persistence\Model\MovimientoContableModel;
use Src\Shared\Domain\Money;

final class EloquentLibroContableRepository implements LibroContableRepository
{
    public function registrar(Asiento $asiento): void
    {
        // Solo INSERT: el trigger `trg_movimiento_inmutable` rechaza UPDATE y
        // DELETE. Las lineas del asiento entran juntas; quien llama ya abrio la
        // transaccion.
        MovimientoContableModel::query()->insert(array_map(
            static fn (MovimientoContable $movimiento): array => [
                'asiento_id' => $movimiento->asientoId,
                'cuenta' => $movimiento->cuenta->value,
                'tipo' => $movimiento->tipo->value,
                'monto' => $movimiento->monto->aDecimal(),
                'moneda' => $movimiento->monto->moneda(),
                'origen_tipo' => $movimiento->origenTipo->value,
                'origen_id' => $movimiento->origenId,
                'anfitrion_id' => $movimiento->anfitrionId,
            ],
            $asiento->movimientos(),
        ));
    }

    public function saldoPorPagarDe(string $anfitrionId, string $moneda = 'BOB'): Money
    {
        // POR_PAGAR_ANFITRION es una cuenta de pasivo: los creditos aumentan la
        // deuda con el anfitrion y los debitos la bajan.
        $creditos = $this->sumar($anfitrionId, $moneda, TipoMovimiento::CREDITO);
        $debitos = $this->sumar($anfitrionId, $moneda, TipoMovimiento::DEBITO);

        return $creditos->menos($debitos);
    }

    private function sumar(string $anfitrionId, string $moneda, TipoMovimiento $tipo): Money
    {
        $suma = MovimientoContableModel::query()
            ->where('anfitrion_id', $anfitrionId)
            ->where('cuenta', CuentaContable::POR_PAGAR_ANFITRION->value)
            ->where('moneda', $moneda)
            ->where('tipo', $tipo->value)
            ->sum('monto');

        return Money::deDecimal(number_format((float) $suma, 2, '.', ''), $moneda);
    }
}
