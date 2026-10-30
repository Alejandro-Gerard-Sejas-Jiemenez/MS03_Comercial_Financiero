<?php

declare(strict_types=1);

namespace Src\Financiero\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Src\Financiero\Application\Port\ConciliacionRepository;
use Src\Financiero\Domain\Entity\Conciliacion;
use Src\Financiero\Domain\Entity\ConciliacionItem;
use Src\Financiero\Domain\Enum\EstadoConciliacion;
use Src\Financiero\Domain\Enum\ResultadoConciliacionItem;
use Src\Financiero\Infrastructure\Persistence\Model\ConciliacionItemModel;
use Src\Financiero\Infrastructure\Persistence\Model\ConciliacionModel;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Money;

final class EloquentConciliacionRepository implements ConciliacionRepository
{
    public function __construct(private readonly UuidGenerator $uuid) {}

    public function buscar(string $conciliacionId): ?Conciliacion
    {
        $fila = ConciliacionModel::query()->with('items')->find($conciliacionId);

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function buscarPorCorte(string $pasarela, DateTimeImmutable $fechaCorte): ?Conciliacion
    {
        $fila = ConciliacionModel::query()
            ->with('items')
            ->where('pasarela', $pasarela)
            ->where('fecha_corte', $fechaCorte->format('Y-m-d'))
            ->first();

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function guardar(Conciliacion $conciliacion): void
    {
        ConciliacionModel::query()->updateOrCreate(
            ['conciliacion_id' => $conciliacion->conciliacionId()],
            [
                'pasarela' => $conciliacion->pasarela(),
                'fecha_corte' => $conciliacion->fechaCorte()->format('Y-m-d'),
                'total_sistema' => $conciliacion->totalSistema()->aDecimal(),
                'total_pasarela' => $conciliacion->totalPasarela()->aDecimal(),
                'estado' => $conciliacion->estado()->value,
                'ejecutada_por' => $conciliacion->ejecutadaPor(),
            ],
        );

        // Los items se identifican por su referencia dentro de la conciliacion;
        // el UUID de la fila lo pone este repositorio en el primer guardado.
        foreach ($conciliacion->items() as $item) {
            ConciliacionItemModel::query()->updateOrCreate(
                [
                    'conciliacion_id' => $conciliacion->conciliacionId(),
                    'referencia_pasarela' => $item->referenciaPasarela(),
                ],
                [
                    'item_id' => $item->itemId() ?? $this->uuid->generar(),
                    'transaccion_id' => $item->transaccionId(),
                    'monto_pasarela' => $item->montoPasarela()->aDecimal(),
                    'resultado' => $item->resultado()->value,
                    'observacion' => $item->observacion(),
                ],
            );
        }
    }

    private function aEntidad(ConciliacionModel $fila): Conciliacion
    {
        $items = $fila->items
            ->map(static fn (ConciliacionItemModel $item): ConciliacionItem => ConciliacionItem::reconstituir(
                itemId: $item->item_id,
                referenciaPasarela: $item->referencia_pasarela,
                montoPasarela: Money::deDecimal($item->monto_pasarela),
                resultado: ResultadoConciliacionItem::from($item->resultado),
                transaccionId: $item->transaccion_id,
                observacion: $item->observacion,
            ))
            ->values()
            ->all();

        return Conciliacion::reconstituir(
            conciliacionId: $fila->conciliacion_id,
            pasarela: $fila->pasarela,
            fechaCorte: new DateTimeImmutable($fila->fecha_corte->format('Y-m-d')),
            totalSistema: Money::deDecimal($fila->total_sistema),
            totalPasarela: Money::deDecimal($fila->total_pasarela),
            estado: EstadoConciliacion::from($fila->estado),
            ejecutadaPor: $fila->ejecutada_por,
            items: $items,
        );
    }
}
