<?php

declare(strict_types=1);

namespace Src\Referencias\Domain;

interface ReservaReferenceRepository
{
    public function buscar(string $reservaId): ?ReservaReference;

    /** Upsert: el evento de MS-02 es la verdad. */
    public function guardar(ReservaReference $reserva): void;
}
