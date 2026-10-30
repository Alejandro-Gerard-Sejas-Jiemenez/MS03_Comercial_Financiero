<?php

declare(strict_types=1);

namespace Src\Shared\Application\Port;

use Src\Shared\Domain\Referencia\AnfitrionReference;

interface AnfitrionReferenceRepository
{
    public function buscar(string $anfitrionId): ?AnfitrionReference;

    /** Upsert: el evento de MS-01 es la verdad, no hay "ya existia". */
    public function guardar(AnfitrionReference $anfitrion): void;

    /**
     * Anfitriones VERIFICADOS. Es la lista que recorre
     * GenerarLiquidaciones sin preguntarle nada a MS-01.
     *
     * @return list<AnfitrionReference>
     */
    public function verificados(): array;
}
