<?php

declare(strict_types=1);

namespace Src\Monetizacion\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Src\Monetizacion\Domain\Concepto;
use Src\Monetizacion\Domain\EstadoRegla;
use Src\Monetizacion\Domain\ReglaMonetizacion;
use Src\Monetizacion\Domain\ReglaMonetizacionRepository;
use Src\Monetizacion\Domain\TipoCalculo;
use Src\Shared\Domain\Money;

/**
 * Traduce entre la fila de Eloquent y la entidad de dominio. Este mapeo es el
 * precio de tener entidades puras con un ORM Active Record, y es donde se paga
 * una sola vez para que el Dominio no dependa de Laravel.
 */
final class EloquentReglaMonetizacionRepository implements ReglaMonetizacionRepository
{
    public function buscar(string $reglaId): ?ReglaMonetizacion
    {
        $fila = ReglaMonetizacionModel::query()->find($reglaId);

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function guardar(ReglaMonetizacion $regla): void
    {
        ReglaMonetizacionModel::query()->updateOrCreate(
            ['regla_id' => $regla->reglaId()],
            [
                'nombre' => $regla->nombre(),
                'concepto' => $regla->concepto()->value,
                'tipo_calculo' => $regla->tipoCalculo()->value,
                'porcentaje' => $regla->porcentaje(),
                'monto_fijo' => $regla->montoFijo()?->aDecimal(),
                'moneda' => $regla->moneda(),
                'categoria_alojamiento' => $regla->categoriaAlojamiento(),
                'nivel_anfitrion' => $regla->nivelAnfitrion(),
                'vigente_desde' => $regla->vigenteDesde()->format('Y-m-d'),
                'vigente_hasta' => $regla->vigenteHasta()?->format('Y-m-d'),
                'estado' => $regla->estado()->value,
                'creado_por' => $regla->creadoPor(),
            ],
        );
    }

    public function vigentes(
        Concepto $concepto,
        DateTimeImmutable $fecha,
        ?string $categoriaAlojamiento = null,
        ?string $nivelAnfitrion = null,
    ): array {
        $dia = $fecha->format('Y-m-d');

        $filas = ReglaMonetizacionModel::query()
            ->where('concepto', $concepto->value)
            ->where('estado', EstadoRegla::ACTIVA->value)
            ->where('vigente_desde', '<=', $dia)
            ->where(static fn (Builder $q): Builder => $q
                ->whereNull('vigente_hasta')
                ->orWhere('vigente_hasta', '>=', $dia))
            // NULL en el alcance significa "aplica a todos".
            ->where(static fn (Builder $q): Builder => $q
                ->whereNull('categoria_alojamiento')
                ->orWhere('categoria_alojamiento', $categoriaAlojamiento))
            ->where(static fn (Builder $q): Builder => $q
                ->whereNull('nivel_anfitrion')
                ->orWhere('nivel_anfitrion', $nivelAnfitrion))
            ->get()
            ->map(fn (ReglaMonetizacionModel $fila): ReglaMonetizacion => $this->aEntidad($fila))
            ->all();

        // La regla de alcance mas especifico gana; a igual especificidad, la que
        // empezo a regir mas tarde.
        usort(
            $filas,
            static fn (ReglaMonetizacion $a, ReglaMonetizacion $b): int => [
                $b->especificidad(), $b->vigenteDesde(),
            ] <=> [$a->especificidad(), $a->vigenteDesde()],
        );

        return $filas;
    }

    public function listar(?Concepto $concepto = null, ?EstadoRegla $estado = null): array
    {
        return ReglaMonetizacionModel::query()
            ->when($concepto !== null, static fn (Builder $q): Builder => $q->where('concepto', $concepto->value))
            ->when($estado !== null, static fn (Builder $q): Builder => $q->where('estado', $estado->value))
            ->orderBy('concepto')
            ->orderByDesc('vigente_desde')
            ->get()
            ->map(fn (ReglaMonetizacionModel $fila): ReglaMonetizacion => $this->aEntidad($fila))
            ->values()
            ->all();
    }

    private function aEntidad(ReglaMonetizacionModel $fila): ReglaMonetizacion
    {
        return ReglaMonetizacion::reconstituir(
            reglaId: $fila->regla_id,
            nombre: $fila->nombre,
            concepto: Concepto::from($fila->concepto),
            tipoCalculo: TipoCalculo::from($fila->tipo_calculo),
            porcentaje: $fila->porcentaje,
            montoFijo: $fila->monto_fijo === null
                ? null
                : Money::deDecimal($fila->monto_fijo, $fila->moneda),
            moneda: $fila->moneda,
            categoriaAlojamiento: $fila->categoria_alojamiento,
            nivelAnfitrion: $fila->nivel_anfitrion,
            // Se convierte a DateTimeImmutable puro: el Dominio no recibe Carbon.
            vigenteDesde: new DateTimeImmutable($fila->vigente_desde->format('Y-m-d')),
            vigenteHasta: $fila->vigente_hasta === null
                ? null
                : new DateTimeImmutable($fila->vigente_hasta->format('Y-m-d')),
            estado: EstadoRegla::from($fila->estado),
            creadoPor: $fila->creado_por,
        );
    }
}
