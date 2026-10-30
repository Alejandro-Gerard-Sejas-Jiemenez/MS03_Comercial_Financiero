<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Port\ReservaReferenceRepository;
use Src\Shared\Domain\Referencia\EstadoReserva;
use Src\Shared\Domain\Referencia\ReservaReference;
use Src\Shared\Infrastructure\Persistence\Model\RefReservaModel;

final class EloquentReservaReferenceRepository implements ReservaReferenceRepository
{
    public function buscar(string $reservaId): ?ReservaReference
    {
        $fila = RefReservaModel::query()->find($reservaId);

        return $fila === null ? null : $this->aEntidad($fila);
    }

    public function guardar(ReservaReference $reserva): void
    {
        RefReservaModel::query()->updateOrCreate(
            ['reserva_id' => $reserva->reservaId],
            [
                'cliente_id' => $reserva->clienteId,
                'anfitrion_id' => $reserva->anfitrionId,
                'alojamiento_id' => $reserva->alojamientoId,
                'categoria_alojamiento' => $reserva->categoriaAlojamiento,
                'fecha_checkin' => $reserva->fechaCheckin->format('Y-m-d'),
                'fecha_checkout' => $reserva->fechaCheckout->format('Y-m-d'),
                'estado' => $reserva->estado->value,
                'sincronizado_en' => DB::raw('now()'),
            ],
        );
    }

    private function aEntidad(RefReservaModel $fila): ReservaReference
    {
        return new ReservaReference(
            reservaId: $fila->reserva_id,
            clienteId: $fila->cliente_id,
            anfitrionId: $fila->anfitrion_id,
            alojamientoId: $fila->alojamiento_id,
            // DateTimeImmutable puro: el Dominio no recibe Carbon.
            fechaCheckin: new DateTimeImmutable($fila->fecha_checkin->format('Y-m-d')),
            fechaCheckout: new DateTimeImmutable($fila->fecha_checkout->format('Y-m-d')),
            estado: EstadoReserva::from($fila->estado),
            categoriaAlojamiento: $fila->categoria_alojamiento,
        );
    }
}
