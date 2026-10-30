<?php

declare(strict_types=1);

namespace Src\Relaciones\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Src\Relaciones\Application\Port\AcuerdoComercialRepository;
use Src\Relaciones\Domain\Entity\AcuerdoComercial;
use Src\Relaciones\Domain\Enum\EstadoAcuerdo;
use Src\Relaciones\Infrastructure\Persistence\Model\AcuerdoComercialModel;

/**
 * Traduce entre la fila de Eloquent y la entidad de dominio. Este mapeo es el
 * precio de tener entidades puras con un ORM Active Record, y es donde se paga
 * una sola vez para que el Dominio no dependa de Laravel.
 */
final class EloquentAcuerdoComercialRepository implements AcuerdoComercialRepository
{
    public function buscar(string $acuerdoId): ?AcuerdoComercial
    {
        $fila = AcuerdoComercialModel::query()->find($acuerdoId);

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function guardar(AcuerdoComercial $acuerdo): void
    {
        AcuerdoComercialModel::query()->updateOrCreate(
            ['acuerdo_id' => $acuerdo->acuerdoId()],
            [
                'anfitrion_id' => $acuerdo->anfitrionId(),
                'regla_comision_id' => $acuerdo->reglaComisionId(),
                'estado' => $acuerdo->estado()->value,
                'fecha_inicio' => $acuerdo->fechaInicio()->format('Y-m-d'),
                'fecha_fin' => $acuerdo->fechaFin()?->format('Y-m-d'),
                'condiciones' => $acuerdo->condiciones(),
                'dias_liquidacion' => $acuerdo->diasLiquidacion(),
                'aprobado_por' => $acuerdo->aprobadoPor(),
                'aprobado_en' => $acuerdo->aprobadoEn()?->format('Y-m-d H:i:sP'),
                'motivo_estado' => $acuerdo->motivoEstado(),
            ],
        );
    }

    public function activoDe(string $anfitrionId): ?AcuerdoComercial
    {
        $fila = AcuerdoComercialModel::query()
            ->where('anfitrion_id', $anfitrionId)
            ->where('estado', EstadoAcuerdo::ACTIVO->value)
            // El indice unico parcial `uq_acuerdo_activo_por_anfitrion` garantiza
            // que haya como maximo una.
            ->first();

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function listar(?EstadoAcuerdo $estado = null, ?string $anfitrionId = null): array
    {
        return AcuerdoComercialModel::query()
            ->when($estado !== null, static fn (Builder $q): Builder => $q->where('estado', $estado->value))
            ->when(
                $anfitrionId !== null,
                static fn (Builder $q): Builder => $q->where('anfitrion_id', $anfitrionId),
            )
            ->orderByDesc('fecha_inicio')
            ->get()
            ->map(fn (AcuerdoComercialModel $fila): AcuerdoComercial => $this->aEntidad($fila))
            ->values()
            ->all();
    }

    private function aEntidad(AcuerdoComercialModel $fila): AcuerdoComercial
    {
        return AcuerdoComercial::reconstituir(
            acuerdoId: $fila->acuerdo_id,
            anfitrionId: $fila->anfitrion_id,
            reglaComisionId: $fila->regla_comision_id,
            estado: EstadoAcuerdo::from($fila->estado),
            // Se convierte a DateTimeImmutable puro: el Dominio no recibe Carbon.
            fechaInicio: new DateTimeImmutable($fila->fecha_inicio->format('Y-m-d')),
            fechaFin: $fila->fecha_fin === null
                ? null
                : new DateTimeImmutable($fila->fecha_fin->format('Y-m-d')),
            condiciones: $fila->condiciones ?? [],
            diasLiquidacion: (int) $fila->dias_liquidacion,
            aprobadoPor: $fila->aprobado_por,
            aprobadoEn: $fila->aprobado_en === null
                ? null
                : new DateTimeImmutable($fila->aprobado_en->format('Y-m-d H:i:s')),
            motivoEstado: $fila->motivo_estado,
        );
    }
}
