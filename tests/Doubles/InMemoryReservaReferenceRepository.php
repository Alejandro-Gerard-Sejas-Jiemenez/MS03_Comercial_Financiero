<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Referencias\Domain\ReservaReference;
use Src\Referencias\Domain\ReservaReferenceRepository;

final class InMemoryReservaReferenceRepository implements ReservaReferenceRepository
{
    /** @var array<string, ReservaReference> */
    private array $reservas = [];

    public function __construct(ReservaReference ...$reservas)
    {
        foreach ($reservas as $reserva) {
            $this->guardar($reserva);
        }
    }

    public function buscar(string $reservaId): ?ReservaReference
    {
        return $this->reservas[$reservaId] ?? null;
    }

    public function guardar(ReservaReference $reserva): void
    {
        $this->reservas[$reserva->reservaId] = $reserva;
    }

    public function cantidad(): int
    {
        return count($this->reservas);
    }
}
