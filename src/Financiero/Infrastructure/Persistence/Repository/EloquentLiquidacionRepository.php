<?php

declare(strict_types=1);

namespace Src\Financiero\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Src\Financiero\Application\Port\LiquidacionRepository;
use Src\Financiero\Domain\Entity\Liquidacion;
use Src\Financiero\Domain\Enum\EstadoLiquidacion;
use Src\Financiero\Infrastructure\Persistence\Model\LiquidacionModel;
use Src\Shared\Domain\Money;

final class EloquentLiquidacionRepository implements LiquidacionRepository
{
    /** Estados en los que el dinero todavia no salio. */
    private const PENDIENTES_DE_PAGO = ['GENERADA', 'APROBADA'];

    public function buscar(string $liquidacionId): ?Liquidacion
    {
        $fila = LiquidacionModel::query()->find($liquidacionId);

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function guardar(Liquidacion $liquidacion): void
    {
        // Las fechas se estampan una vez, al aprobar y al pagar, y no se pisan despues.
        $previa = LiquidacionModel::query()->whereKey($liquidacion->liquidacionId())->first(['aprobado_en', 'pagado_en']);
        $aprobadoEn = $previa?->aprobado_en
            ?? (in_array($liquidacion->estado(), [EstadoLiquidacion::APROBADA, EstadoLiquidacion::PAGADA], true) ? now() : null);
        $pagadoEn = $previa?->pagado_en ?? ($liquidacion->estaPagada() ? now() : null);

        LiquidacionModel::query()->updateOrCreate(
            ['liquidacion_id' => $liquidacion->liquidacionId()],
            [
                'anfitrion_id' => $liquidacion->anfitrionId(),
                'metodo_id' => $liquidacion->metodoId(),
                'periodo_desde' => $liquidacion->periodoDesde()->format('Y-m-d'),
                'periodo_hasta' => $liquidacion->periodoHasta()->format('Y-m-d'),
                'total_bruto' => $liquidacion->totalBruto()->aDecimal(),
                'total_comisiones' => $liquidacion->totalComisiones()->aDecimal(),
                'total_reembolsos' => $liquidacion->totalReembolsos()->aDecimal(),
                'total_neto' => $liquidacion->totalNeto()->aDecimal(),
                'moneda' => $liquidacion->moneda(),
                'estado' => $liquidacion->estado()->value,
                'aprobado_por' => $liquidacion->aprobadoPor(),
                'aprobado_en' => $aprobadoEn,
                'pagado_en' => $pagadoEn,
                'referencia_transferencia' => $liquidacion->referenciaTransferencia(),
            ],
        );
    }

    public function todas(?EstadoLiquidacion $estado = null, ?string $anfitrionId = null, int $limite = 100): array
    {
        return LiquidacionModel::query()
            ->when($estado !== null, static fn (Builder $q): Builder => $q->where('estado', $estado?->value))
            ->when($anfitrionId !== null, static fn (Builder $q): Builder => $q->where('anfitrion_id', $anfitrionId))
            ->orderByDesc('periodo_hasta')
            ->limit($limite)
            ->get()
            ->map(fn (LiquidacionModel $fila): Liquidacion => $this->aEntidad($fila))
            ->values()
            ->all();
    }

    public function deAnfitrion(
        string $anfitrionId,
        ?EstadoLiquidacion $estado = null,
        int $limite = 50,
    ): array {
        return LiquidacionModel::query()
            ->where('anfitrion_id', $anfitrionId)
            ->when(
                $estado !== null,
                static fn (Builder $q): Builder => $q->where('estado', $estado?->value),
            )
            ->orderByDesc('periodo_hasta')
            ->limit($limite)
            ->get()
            ->map(fn (LiquidacionModel $fila): Liquidacion => $this->aEntidad($fila))
            ->values()
            ->all();
    }

    public function pendientesDePago(string $anfitrionId): array
    {
        return LiquidacionModel::query()
            ->where('anfitrion_id', $anfitrionId)
            ->whereIn('estado', self::PENDIENTES_DE_PAGO)
            ->orderBy('periodo_hasta')
            ->get()
            ->map(fn (LiquidacionModel $fila): Liquidacion => $this->aEntidad($fila))
            ->values()
            ->all();
    }

    private function aEntidad(LiquidacionModel $fila): Liquidacion
    {
        $moneda = $fila->moneda;

        return Liquidacion::reconstituir(
            liquidacionId: $fila->liquidacion_id,
            anfitrionId: $fila->anfitrion_id,
            metodoId: $fila->metodo_id,
            periodoDesde: new DateTimeImmutable($fila->periodo_desde->format('Y-m-d')),
            periodoHasta: new DateTimeImmutable($fila->periodo_hasta->format('Y-m-d')),
            totalBruto: Money::deDecimal($fila->total_bruto, $moneda),
            totalComisiones: Money::deDecimal($fila->total_comisiones, $moneda),
            totalReembolsos: Money::deDecimal($fila->total_reembolsos, $moneda),
            estado: EstadoLiquidacion::from($fila->estado),
            aprobadoPor: $fila->aprobado_por,
            referenciaTransferencia: $fila->referencia_transferencia,
        );
    }
}
