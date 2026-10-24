<?php

declare(strict_types=1);

namespace Src\Referencias\Domain;

interface AnfitrionReferenceRepository
{
    public function buscar(string $anfitrionId): ?AnfitrionReference;

    /** Upsert: el evento de MS-01 es la verdad, no hay "ya existia". */
    public function guardar(AnfitrionReference $anfitrion): void;

    /**
     * Anfitriones VERIFICADOS. Es la lista que recorre CU-LIQ-04
     * GenerarLiquidaciones sin preguntarle nada a MS-01.
     *
     * @return list<AnfitrionReference>
     */
    public function verificados(): array;
}
