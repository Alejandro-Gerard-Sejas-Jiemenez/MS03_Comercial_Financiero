<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Shared\Domain\DomainEvent;
use Src\Shared\Domain\EventoOutbox;
use Src\Shared\Domain\OutboxRepository;

final class InMemoryOutboxRepository implements OutboxRepository
{
    /** @var array<string, array{evento: EventoOutbox, publicado: bool}> */
    private array $filas = [];

    private int $secuencia = 0;

    public function agregar(DomainEvent $evento): void
    {
        $id = sprintf('outbox-%03d', ++$this->secuencia);

        $this->filas[$id] = [
            'evento' => new EventoOutbox(
                $id,
                $evento->agregadoTipo(),
                $evento->agregadoId(),
                $evento->tipoEvento(),
                $evento->payload(),
                $evento->sagaId(),
                0,
            ),
            'publicado' => false,
        ];
    }

    public function pendientes(int $limite): array
    {
        $pendientes = [];

        foreach ($this->filas as $fila) {
            if (count($pendientes) >= $limite) {
                break;
            }

            if (! $fila['publicado']) {
                $pendientes[] = $fila['evento'];
            }
        }

        return $pendientes;
    }

    public function marcarPublicado(string $eventoId): void
    {
        $this->filas[$eventoId]['publicado'] = true;
    }

    public function registrarIntento(string $eventoId): void
    {
        $anterior = $this->filas[$eventoId]['evento'];

        $this->filas[$eventoId]['evento'] = new EventoOutbox(
            $anterior->eventoId,
            $anterior->agregadoTipo,
            $anterior->agregadoId,
            $anterior->tipoEvento,
            $anterior->payload,
            $anterior->sagaId,
            $anterior->intentos + 1,
        );
    }

    public function intentosDe(string $eventoId): int
    {
        return $this->filas[$eventoId]['evento']->intentos;
    }

    public function cantidadPendientes(): int
    {
        return count($this->pendientes(PHP_INT_MAX));
    }
}
