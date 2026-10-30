<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Port;

use DateTimeImmutable;
use Src\Financiero\Domain\Entity\Conciliacion;

interface ConciliacionRepository
{
    public function buscar(string $conciliacionId): ?Conciliacion;

    /** Conciliacion ya existente de ese corte: la base no admite dos del mismo dia. */
    public function buscarPorCorte(string $pasarela, DateTimeImmutable $fechaCorte): ?Conciliacion;

    /** Guarda la conciliacion y sus items. */
    public function guardar(Conciliacion $conciliacion): void;
}
