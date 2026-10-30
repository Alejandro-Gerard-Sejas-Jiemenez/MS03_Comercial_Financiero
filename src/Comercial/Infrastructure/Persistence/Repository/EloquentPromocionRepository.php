<?php

declare(strict_types=1);

namespace Src\Comercial\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Src\Comercial\Application\Port\PromocionRepository;
use Src\Comercial\Domain\Entity\Promocion;
use Src\Comercial\Domain\Enum\EstadoPromocion;
use Src\Comercial\Domain\Enum\RolCreador;
use Src\Comercial\Domain\Enum\TipoDescuento;
use Src\Comercial\Domain\Enum\TipoPromocion;
use Src\Comercial\Infrastructure\Persistence\Model\PromocionAlojamientoModel;
use Src\Comercial\Infrastructure\Persistence\Model\PromocionModel;
use Src\Monetizacion\Domain\Enum\FinanciamientoDescuento;

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
        $r = $promocion->retrato();

        PromocionModel::query()->updateOrCreate(
            ['promocion_id' => $r['promocion_id']],
            [
                'codigo' => $r['codigo'],
                'nombre' => $r['nombre'],
                'tipo' => $r['tipo'],
                'tipo_descuento' => $r['tipo_descuento'],
                'valor' => $r['valor'],
                'moneda' => $r['moneda'],
                'creada_por_rol' => $r['creada_por_rol'],
                'creada_por' => $r['creada_por'],
                'anfitrion_id' => $r['anfitrion_id'],
                'financiada_por' => $r['financiada_por'],
                'min_noches' => $r['min_noches'],
                'vigente_desde' => $r['vigente_desde']->format('Y-m-d H:i:sP'),
                'vigente_hasta' => $r['vigente_hasta']->format('Y-m-d H:i:sP'),
                'max_usos' => $r['max_usos'],
                'usos_actuales' => $r['usos_actuales'],
                'estado' => $r['estado'],
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
     * @param  list<PromocionModel>  $filas
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
     * @param  list<string>  $promocionIds
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
        $deseados = $promocion->retrato()['alojamientos'];
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
