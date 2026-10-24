<?php

declare(strict_types=1);

namespace Src\Monetizacion\Domain;

use DateTimeImmutable;

/**
 * Interfaz definida por el Dominio e implementada por Infraestructura
 * (Principio de Inversion de Dependencias).
 */
interface ReglaMonetizacionRepository
{
    public function buscar(string $reglaId): ?ReglaMonetizacion;

    public function guardar(ReglaMonetizacion $regla): void;

    /**
     * Reglas ACTIVAS de ese concepto vigentes en la fecha, cuyo alcance incluye
     * la categoria y el nivel dados. Ordenadas de mas especifica a mas general.
     *
     * @return list<ReglaMonetizacion>
     */
    public function vigentes(
        Concepto $concepto,
        DateTimeImmutable $fecha,
        ?string $categoriaAlojamiento = null,
        ?string $nivelAnfitrion = null,
    ): array;

    /** @return list<ReglaMonetizacion> */
    public function listar(?Concepto $concepto = null, ?EstadoRegla $estado = null): array;
}
