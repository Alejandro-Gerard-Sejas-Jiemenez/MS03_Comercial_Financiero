<?php

declare(strict_types=1);

namespace Src\Shared\Application\Port;

use Src\Shared\Domain\Referencia\ReservaReference;

interface ReservaReferenceRepository
{
    public function buscar(string $reservaId): ?ReservaReference;

    /** Upsert: el evento de MS-02 es la verdad. */
    public function guardar(ReservaReference $reserva): void;
}
