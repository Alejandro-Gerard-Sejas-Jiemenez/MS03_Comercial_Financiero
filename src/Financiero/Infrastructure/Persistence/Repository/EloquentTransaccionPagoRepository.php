<?php

declare(strict_types=1);

namespace Src\Financiero\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Src\Financiero\Application\Port\TransaccionPagoRepository;
use Src\Financiero\Domain\Entity\TransaccionPago;
use Src\Financiero\Domain\Enum\EstadoTransaccion;
use Src\Financiero\Domain\Enum\TipoTransaccion;
use Src\Financiero\Infrastructure\Persistence\Model\TransaccionPagoModel;
use Src\Shared\Domain\Money;

final class EloquentTransaccionPagoRepository implements TransaccionPagoRepository
{
    public function agregar(TransaccionPago $transaccion): void
    {
        // Solo INSERT: el trigger `trg_transaccion_inmutable` rechaza UPDATE.
        TransaccionPagoModel::query()->create([
            'transaccion_id' => $transaccion->transaccionId,
            'pago_id' => $transaccion->pagoId,
            'tipo' => $transaccion->tipo->value,
            'monto' => $transaccion->monto->aDecimal(),
            'estado' => $transaccion->estado->value,
            'referencia_pasarela' => $transaccion->referenciaPasarela,
            'respuesta_pasarela' => $transaccion->respuestaPasarela,
        ]);
    }

    public function buscarPorReferencia(string $referenciaPasarela): ?TransaccionPago
    {
        $fila = TransaccionPagoModel::query()
            ->where('referencia_pasarela', $referenciaPasarela)
            ->first();

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function delPago(string $pagoId): array
    {
        return TransaccionPagoModel::query()
            ->where('pago_id', $pagoId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (TransaccionPagoModel $fila): TransaccionPago => $this->aEntidad($fila))
            ->values()
            ->all();
    }

    public function exitosasDelDia(string $pasarela, DateTimeImmutable $fechaCorte): array
    {
        return TransaccionPagoModel::query()
            ->where('transaccion_pago.estado', EstadoTransaccion::EXITOSA->value)
            ->whereNotNull('referencia_pasarela')
            ->whereDate('transaccion_pago.created_at', $fechaCorte->format('Y-m-d'))
            // La pasarela es un dato del pago, no de la transaccion.
            ->whereExists(static function ($query) use ($pasarela): void {
                $query->selectRaw('1')
                    ->from('pago')
                    ->whereColumn('pago.pago_id', 'transaccion_pago.pago_id')
                    ->where('pago.pasarela', $pasarela);
            })
            ->orderBy('transaccion_pago.created_at')
            ->get()
            ->map(fn (TransaccionPagoModel $fila): TransaccionPago => $this->aEntidad($fila))
            ->values()
            ->all();
    }

    private function aEntidad(TransaccionPagoModel $fila): TransaccionPago
    {
        return TransaccionPago::registrar(
            transaccionId: $fila->transaccion_id,
            pagoId: $fila->pago_id,
            tipo: TipoTransaccion::from($fila->tipo),
            monto: Money::deDecimal($fila->monto),
            estado: EstadoTransaccion::from($fila->estado),
            referenciaPasarela: $fila->referencia_pasarela,
            respuestaPasarela: $fila->respuesta_pasarela,
        );
    }
}
