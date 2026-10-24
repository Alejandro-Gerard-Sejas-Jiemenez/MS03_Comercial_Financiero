<?php

declare(strict_types=1);

namespace Src\Pagos\Infrastructure\Persistence;

use Src\Pagos\Domain\EstadoReembolso;
use Src\Pagos\Domain\MotivoReembolso;
use Src\Pagos\Domain\Reembolso;
use Src\Pagos\Domain\ReembolsoRepository;
use Src\Shared\Domain\Money;

final class EloquentReembolsoRepository implements ReembolsoRepository
{
    public function buscar(string $reembolsoId): ?Reembolso
    {
        $fila = ReembolsoModel::query()->find($reembolsoId);

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function guardar(Reembolso $reembolso): void
    {
        ReembolsoModel::query()->updateOrCreate(
            ['reembolso_id' => $reembolso->reembolsoId()],
            [
                'pago_id' => $reembolso->pagoId(),
                'transaccion_id' => $reembolso->transaccionId(),
                'monto' => $reembolso->monto()->aDecimal(),
                'motivo' => $reembolso->motivo()->value,
                'detalle' => $reembolso->detalle(),
                'estado' => $reembolso->estado()->value,
                'solicitado_por' => $reembolso->solicitadoPor(),
            ],
        );
    }

    public function delPago(string $pagoId): array
    {
        return ReembolsoModel::query()
            ->where('pago_id', $pagoId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (ReembolsoModel $fila): Reembolso => $this->aEntidad($fila))
            ->values()
            ->all();
    }

    public function aprobadosPendientesDeProcesar(int $lote = 50): array
    {
        return ReembolsoModel::query()
            ->where('estado', EstadoReembolso::APROBADO->value)
            ->orderBy('created_at')
            ->limit($lote)
            ->get()
            ->map(fn (ReembolsoModel $fila): Reembolso => $this->aEntidad($fila))
            ->values()
            ->all();
    }

    private function aEntidad(ReembolsoModel $fila): Reembolso
    {
        return Reembolso::reconstituir(
            reembolsoId: $fila->reembolso_id,
            pagoId: $fila->pago_id,
            // `reembolso` no tiene columna de moneda: hereda la del pago.
            monto: Money::deDecimal($fila->monto, $this->monedaDelPago($fila->pago_id)),
            motivo: MotivoReembolso::from($fila->motivo),
            estado: EstadoReembolso::from($fila->estado),
            detalle: $fila->detalle,
            solicitadoPor: $fila->solicitado_por,
            transaccionId: $fila->transaccion_id,
        );
    }

    private function monedaDelPago(string $pagoId): string
    {
        return PagoModel::query()->whereKey($pagoId)->value('moneda') ?? 'BOB';
    }
}
