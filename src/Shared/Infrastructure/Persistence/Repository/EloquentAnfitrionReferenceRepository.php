<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence\Repository;

use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Port\AnfitrionReferenceRepository;
use Src\Shared\Domain\Referencia\AnfitrionReference;
use Src\Shared\Domain\Referencia\EstadoVerificacionAnfitrion;
use Src\Shared\Infrastructure\Persistence\Model\RefAnfitrionModel;

final class EloquentAnfitrionReferenceRepository implements AnfitrionReferenceRepository
{
    public function buscar(string $anfitrionId): ?AnfitrionReference
    {
        $fila = RefAnfitrionModel::query()->find($anfitrionId);

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function guardar(AnfitrionReference $anfitrion): void
    {
        RefAnfitrionModel::query()->updateOrCreate(
            ['anfitrion_id' => $anfitrion->anfitrionId],
            [
                'nombre_completo' => $anfitrion->nombreCompleto,
                'nit' => $anfitrion->nit,
                'razon_social' => $anfitrion->razonSocial,
                'estado_verificacion' => $anfitrion->estadoVerificacion->value,
                'sincronizado_en' => DB::raw('now()'),
            ],
        );
    }

    public function verificados(): array
    {
        return RefAnfitrionModel::query()
            ->where('estado_verificacion', EstadoVerificacionAnfitrion::VERIFICADO->value)
            ->orderBy('nombre_completo')
            ->get()
            ->map(fn (RefAnfitrionModel $fila): AnfitrionReference => $this->aEntidad($fila))
            ->values()
            ->all();
    }

    private function aEntidad(RefAnfitrionModel $fila): AnfitrionReference
    {
        return new AnfitrionReference(
            anfitrionId: $fila->anfitrion_id,
            nombreCompleto: $fila->nombre_completo,
            estadoVerificacion: EstadoVerificacionAnfitrion::from($fila->estado_verificacion),
            nit: $fila->nit,
            razonSocial: $fila->razon_social,
        );
    }
}
