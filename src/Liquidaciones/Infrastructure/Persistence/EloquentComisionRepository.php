<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Infrastructure\Persistence;

use DateTimeImmutable;
use Src\Liquidaciones\Domain\Comision;
use Src\Liquidaciones\Domain\ComisionRepository;
use Src\Liquidaciones\Domain\EstadoComision;
use Src\Shared\Domain\Money;

final class EloquentComisionRepository implements ComisionRepository
{
    public function buscar(string $comisionId): ?Comision
    {
        $fila = ComisionModel::query()->find($comisionId);

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function buscarPorPago(string $pagoId): ?Comision
    {
        $fila = ComisionModel::query()->where('pago_id', $pagoId)->first();

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function buscarPorReserva(string $reservaId): ?Comision
    {
        $fila = ComisionModel::query()->where('reserva_id', $reservaId)->first();

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function guardar(Comision $comision): void
    {
        ComisionModel::query()->updateOrCreate(
            ['comision_id' => $comision->comisionId()],
            [
                'pago_id' => $comision->pagoId(),
                'reserva_id' => $comision->reservaId(),
                'anfitrion_id' => $comision->anfitrionId(),
                'regla_id' => $comision->reglaId(),
                'acuerdo_id' => $comision->acuerdoId(),
                'liquidacion_id' => $comision->liquidacionId(),
                'base_comisionable' => $comision->baseComisionable()->aDecimal(),
                'porcentaje_aplicado' => $comision->porcentajeAplicado(),
                'monto_comision' => $comision->montoComision()->aDecimal(),
                'monto_neto_anfitrion' => $comision->montoNetoAnfitrion()->aDecimal(),
                'moneda' => $comision->moneda(),
                'estado' => $comision->estado()->value,
                'liquidable_desde' => $comision->liquidableDesde()?->format('Y-m-d'),
            ],
        );
    }

    public function retenidasDeReserva(string $reservaId): array
    {
        return ComisionModel::query()
            ->where('reserva_id', $reservaId)
            ->where('estado', EstadoComision::RETENIDA->value)
            ->get()
            ->map(fn (ComisionModel $fila): Comision => $this->aEntidad($fila))
            ->values()
            ->all();
    }

    public function liquidablesDe(string $anfitrionId, DateTimeImmutable $hasta): array
    {
        // Usa el indice `idx_comision_liquidable (anfitrion_id, estado, liquidable_desde)`.
        return ComisionModel::query()
            ->where('anfitrion_id', $anfitrionId)
            ->where('estado', EstadoComision::LIQUIDABLE->value)
            ->whereNotNull('liquidable_desde')
            ->where('liquidable_desde', '<=', $hasta->format('Y-m-d'))
            ->orderBy('liquidable_desde')
            ->get()
            ->map(fn (ComisionModel $fila): Comision => $this->aEntidad($fila))
            ->values()
            ->all();
    }

    public function deLiquidacion(string $liquidacionId): array
    {
        return ComisionModel::query()
            ->where('liquidacion_id', $liquidacionId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (ComisionModel $fila): Comision => $this->aEntidad($fila))
            ->values()
            ->all();
    }

    private function aEntidad(ComisionModel $fila): Comision
    {
        $moneda = $fila->moneda;

        return Comision::reconstituir(
            comisionId: $fila->comision_id,
            pagoId: $fila->pago_id,
            reservaId: $fila->reserva_id,
            anfitrionId: $fila->anfitrion_id,
            reglaId: $fila->regla_id,
            acuerdoId: $fila->acuerdo_id,
            liquidacionId: $fila->liquidacion_id,
            baseComisionable: Money::deDecimal($fila->base_comisionable, $moneda),
            porcentajeAplicado: $fila->porcentaje_aplicado,
            montoComision: Money::deDecimal($fila->monto_comision, $moneda),
            montoNetoAnfitrion: Money::deDecimal($fila->monto_neto_anfitrion, $moneda),
            estado: EstadoComision::from($fila->estado),
            // DateTimeImmutable puro: el Dominio no recibe Carbon.
            liquidableDesde: $fila->liquidable_desde === null
                ? null
                : new DateTimeImmutable($fila->liquidable_desde->format('Y-m-d')),
        );
    }
}
