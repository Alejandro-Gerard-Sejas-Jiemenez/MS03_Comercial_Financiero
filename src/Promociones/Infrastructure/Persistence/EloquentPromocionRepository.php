<?php

declare(strict_types=1);

namespace Src\Promociones\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Src\Monetizacion\Domain\FinanciamientoDescuento;
use Src\Promociones\Domain\EstadoPromocion;
use Src\Promociones\Domain\Promocion;
use Src\Promociones\Domain\PromocionRepository;
use Src\Promociones\Domain\RolCreador;
use Src\Promociones\Domain\TipoDescuento;
use Src\Promociones\Domain\TipoPromocion;

/**
 * Traduce entre las filas de Eloquent (`promocion` y `promocion_alojamiento`) y
 * la entidad de dominio. Este mapeo es el precio de tener entidades puras con
 * un ORM Active Record, y es donde se paga una sola vez para que el Dominio no
 * dependa de Laravel.
 */
final class EloquentPromocionRepository implements PromocionRepository
{
    public function buscar(string $promocionId): ?Promocion
    {
        $fila = PromocionModel::query()->find($promocionId);

        return $fila === null ? null : $this->aEntidad($fila, $this->alojamientosDe([$promocionId]));
    }

    public function buscarPorCodigo(string $codigo): ?Promocion
    {
        $fila = PromocionModel::query()
            ->where('codigo', strtoupper(trim($codigo)))
            ->first();

        return $fila === null
            ? null
            : $this->aEntidad($fila, $this->alojamientosDe([$fila->promocion_id]));
    }

    public function guardar(Promocion $promocion): void
    {
        PromocionModel::query()->updateOrCreate(
            ['promocion_id' => $promocion->promocionId()],
            [
                'codigo' => $promocion->codigo(),
                'nombre' => $promocion->nombre(),
                'tipo' => $promocion->tipo()->value,
                'tipo_descuento' => $promocion->tipoDescuento()->value,
                'valor' => $promocion->valor(),
                'moneda' => $promocion->moneda(),
                'creada_por_rol' => $promocion->creadaPorRol()->value,
                'creada_por' => $promocion->creadaPor(),
                'anfitrion_id' => $promocion->anfitrionId(),
                'financiada_por' => $promocion->financiadaPor()->value,
                'min_noches' => $promocion->minNoches(),
                'vigente_desde' => $promocion->vigenteDesde()->format('Y-m-d H:i:sP'),
                'vigente_hasta' => $promocion->vigenteHasta()->format('Y-m-d H:i:sP'),
                'max_usos' => $promocion->maxUsos(),
                'usos_actuales' => $promocion->usosActuales(),
                'estado' => $promocion->estado()->value,
            ],
        );

        $this->sincronizarAlojamientos($promocion);
    }

    public function vigentesSinCupon(
        DateTimeImmutable $fecha,
        ?string $alojamientoId = null,
        ?string $anfitrionId = null,
    ): array {
        $momento = $fecha->format('Y-m-d H:i:sP');

        $filas = PromocionModel::query()
            ->whereNull('codigo')
            ->where('estado', EstadoPromocion::ACTIVA->value)
            ->where('vigente_desde', '<=', $momento)
            ->where('vigente_hasta', '>=', $momento)
            ->where(static fn (Builder $q): Builder => $q
                ->whereNull('max_usos')
                ->orWhereColumn('usos_actuales', '<', 'max_usos'))
            // `anfitrion_id` NULL es una promocion de la plataforma: alcanza a todos.
            ->where(static fn (Builder $q): Builder => $q
                ->whereNull('anfitrion_id')
                ->orWhere('anfitrion_id', $anfitrionId))
            // Sin filas en `promocion_alojamiento` alcanza a todos los del alcance.
            ->where(function (Builder $q) use ($alojamientoId): void {
                $q->whereNotExists(static fn (QueryBuilder $sub): QueryBuilder => $sub
                    ->selectRaw('1')
                    ->from('promocion_alojamiento')
                    ->whereColumn('promocion_alojamiento.promocion_id', 'promocion.promocion_id'));

                if ($alojamientoId !== null) {
                    $q->orWhereExists(static fn (QueryBuilder $sub): QueryBuilder => $sub
                        ->selectRaw('1')
                        ->from('promocion_alojamiento')
                        ->whereColumn('promocion_alojamiento.promocion_id', 'promocion.promocion_id')
                        ->where('promocion_alojamiento.alojamiento_id', $alojamientoId));
                }
            })
            ->orderByDesc('vigente_desde')
            ->get();

        return $this->aEntidades($filas->all());
    }

    public function vencibles(DateTimeImmutable $fecha): array
    {
        $filas = PromocionModel::query()
            ->where('vigente_hasta', '<', $fecha->format('Y-m-d H:i:sP'))
            ->whereNotIn('estado', [
                EstadoPromocion::VENCIDA->value,
                EstadoPromocion::CANCELADA->value,
            ])
            ->orderBy('vigente_hasta')
            ->get();

        return $this->aEntidades($filas->all());
    }

    public function listar(
        ?string $anfitrionId = null,
        ?EstadoPromocion $estado = null,
        ?TipoPromocion $tipo = null,
    ): array {
        $filas = PromocionModel::query()
            ->when(
                $anfitrionId !== null,
                static fn (Builder $q): Builder => $q->where('anfitrion_id', $anfitrionId),
            )
            ->when(
                $estado !== null,
                static fn (Builder $q): Builder => $q->where('estado', $estado->value),
            )
            ->when(
                $tipo !== null,
                static fn (Builder $q): Builder => $q->where('tipo', $tipo->value),
            )
            ->orderByDesc('vigente_desde')
            ->get();

        return $this->aEntidades($filas->all());
    }

    /**
     * @param list<PromocionModel> $filas
     * @return list<Promocion>
     */
    private function aEntidades(array $filas): array
    {
        $alojamientos = $this->alojamientosDe(
            array_map(static fn (PromocionModel $fila): string => $fila->promocion_id, $filas),
        );

        return array_values(array_map(
            fn (PromocionModel $fila): Promocion => $this->aEntidad($fila, $alojamientos),
            $filas,
        ));
    }

    /** @param array<string, list<string>> $alojamientos */
    private function aEntidad(PromocionModel $fila, array $alojamientos): Promocion
    {
        return Promocion::reconstituir(
            promocionId: $fila->promocion_id,
            codigo: $fila->codigo,
            nombre: $fila->nombre,
            tipo: TipoPromocion::from($fila->tipo),
            tipoDescuento: TipoDescuento::from($fila->tipo_descuento),
            valor: (string) $fila->valor,
            moneda: $fila->moneda,
            creadaPorRol: RolCreador::from($fila->creada_por_rol),
            creadaPor: $fila->creada_por,
            anfitrionId: $fila->anfitrion_id,
            financiadaPor: FinanciamientoDescuento::from($fila->financiada_por),
            minNoches: $fila->min_noches,
            // Se convierte a DateTimeImmutable puro: el Dominio no recibe Carbon.
            vigenteDesde: new DateTimeImmutable($fila->vigente_desde->format('Y-m-d H:i:sP')),
            vigenteHasta: new DateTimeImmutable($fila->vigente_hasta->format('Y-m-d H:i:sP')),
            maxUsos: $fila->max_usos,
            usosActuales: $fila->usos_actuales,
            estado: EstadoPromocion::from($fila->estado),
            alojamientos: $alojamientos[$fila->promocion_id] ?? [],
        );
    }

    /**
     * Carga los alojamientos de varias promociones en una sola consulta, para
     * no caer en N+1.
     *
     * @param list<string> $promocionIds
     * @return array<string, list<string>>
     */
    private function alojamientosDe(array $promocionIds): array
    {
        if ($promocionIds === []) {
            return [];
        }

        $porPromocion = [];

        $filas = PromocionAlojamientoModel::query()
            ->whereIn('promocion_id', $promocionIds)
            ->orderBy('alojamiento_id')
            ->get();

        foreach ($filas as $fila) {
            $porPromocion[$fila->promocion_id][] = $fila->alojamiento_id;
        }

        return $porPromocion;
    }

    /**
     * Deja `promocion_alojamiento` igual a la lista de la entidad: borra las
     * filas que sobran e inserta las que faltan.
     */
    private function sincronizarAlojamientos(Promocion $promocion): void
    {
        $deseados = $promocion->alojamientos();
        $actuales = $this->alojamientosDe([$promocion->promocionId()])[$promocion->promocionId()] ?? [];

        $sobran = array_values(array_diff($actuales, $deseados));
        $faltan = array_values(array_diff($deseados, $actuales));

        if ($sobran !== []) {
            PromocionAlojamientoModel::query()
                ->where('promocion_id', $promocion->promocionId())
                ->whereIn('alojamiento_id', $sobran)
                ->delete();
        }

        if ($faltan === []) {
            return;
        }

        PromocionAlojamientoModel::query()->insert(array_map(
            static fn (string $alojamientoId): array => [
                'promocion_id' => $promocion->promocionId(),
                'alojamiento_id' => $alojamientoId,
            ],
            $faltan,
        ));
    }
}
