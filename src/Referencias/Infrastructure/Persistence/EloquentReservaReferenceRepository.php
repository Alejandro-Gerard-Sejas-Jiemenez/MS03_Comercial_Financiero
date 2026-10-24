<?php

declare(strict_types=1);

namespace Src\Referencias\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Referencias\Domain\EstadoReserva;
use Src\Referencias\Domain\ReservaReference;
use Src\Referencias\Domain\ReservaReferenceRepository;

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
