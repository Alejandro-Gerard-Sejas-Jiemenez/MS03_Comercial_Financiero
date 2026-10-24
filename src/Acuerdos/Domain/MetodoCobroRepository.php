<?php

declare(strict_types=1);

namespace Src\Acuerdos\Domain;

/**
 * Interfaz definida por el Dominio e implementada por Infraestructura.
 */
interface MetodoCobroRepository
{
    public function buscar(string $metodoId): ?MetodoCobro;

    public function guardar(MetodoCobro $metodo): void;

    /** @return list<MetodoCobro> */
    public function deAnfitrion(string $anfitrionId): array;

    /** El metodo principal del anfitrion, verificado o no, o null. */
    public function principalDe(string $anfitrionId): ?MetodoCobro;
}
