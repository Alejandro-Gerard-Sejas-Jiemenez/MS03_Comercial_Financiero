<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\DomainEvent;
use Src\Shared\Domain\EventoOutbox;
use Src\Shared\Domain\OutboxRepository;

final class EloquentOutboxRepository implements OutboxRepository
{
    public function __construct(private readonly UuidGenerator $uuid)
    {
    }

    public function agregar(DomainEvent $evento): void
    {
        OutboxEventoModel::create([
            'evento_id' => $this->uuid->generar(),
            'agregado_tipo' => $evento->agregadoTipo(),
            'agregado_id' => $evento->agregadoId(),
            'tipo_evento' => $evento->tipoEvento(),
            'payload' => $evento->payload(),
            'saga_id' => $evento->sagaId(),
        ]);
    }

    public function pendientes(int $limite): array
    {
        return OutboxEventoModel::query()
            ->whereNull('publicado_en')
            ->orderBy('created_at')
            ->limit($limite)
            ->get()
            ->map(static fn (OutboxEventoModel $fila): EventoOutbox => new EventoOutbox(
                $fila->evento_id,
                $fila->agregado_tipo,
                $fila->agregado_id,
                $fila->tipo_evento,
                $fila->payload,
                $fila->saga_id,
                $fila->intentos,
            ))
            ->values()
            ->all();
    }

    public function marcarPublicado(string $eventoId): void
    {
        OutboxEventoModel::query()
            ->whereKey($eventoId)
            ->update(['publicado_en' => DB::raw('now()')]);
    }

    public function registrarIntento(string $eventoId): void
    {
        OutboxEventoModel::query()->whereKey($eventoId)->increment('intentos');
    }
}
