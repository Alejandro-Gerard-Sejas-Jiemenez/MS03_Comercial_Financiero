<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\Port;

use DateTimeImmutable;

/**
 * Puerto de lectura para los reportes financieros (CU-LIQ-10).
 *
 * Son agregaciones (SUM, GROUP BY) sobre varias tablas: cargarlas como
 * entidades para sumarlas en PHP seria caro y sin sentido. La implementacion
 * vive en Infraestructura y devuelve estructuras de datos simples, con lo que
 * la capa de Aplicacion sigue sin saber SQL.
 */
interface ConsultaDeReportes
{
    public function ingresosPorPeriodo(
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
        string $moneda = 'BOB',
    ): ResumenDeIngresos;

    /** @return list<IngresoPorCategoria> */
    public function ingresosPorCategoria(
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
        string $moneda = 'BOB',
    ): array;
}
