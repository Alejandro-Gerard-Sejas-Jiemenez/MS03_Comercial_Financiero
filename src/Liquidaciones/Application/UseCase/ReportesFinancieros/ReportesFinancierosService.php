<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\ReportesFinancieros;

use DateTimeImmutable;
use DomainException;
use Src\Liquidaciones\Application\Port\ConsultaDeReportes;
use Src\Liquidaciones\Application\Port\IngresoPorCategoria;
use Src\Liquidaciones\Application\Port\ResumenDeIngresos;

/**
 * CU-LIQ-10 ReportesFinancieros (QRY, REST).
 *
 * Ingresos y comisiones por periodo y por categoria. Los consume el panel del
 * Administrador y tambien MS-04: F11 para la tarifa sugerida y F13 para las
 * consultas del copiloto, siempre de solo lectura.
 */
final class ReportesFinancierosService
{
    public function __construct(private readonly ConsultaDeReportes $consulta)
    {
    }

    public function ingresos(
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
        string $moneda = 'BOB',
    ): ResumenDeIngresos {
        $this->validarPeriodo($desde, $hasta);

        return $this->consulta->ingresosPorPeriodo($desde, $hasta, $moneda);
    }

    /** @return list<IngresoPorCategoria> */
    public function porCategoria(
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
        string $moneda = 'BOB',
    ): array {
        $this->validarPeriodo($desde, $hasta);

        return $this->consulta->ingresosPorCategoria($desde, $hasta, $moneda);
    }

    private function validarPeriodo(DateTimeImmutable $desde, DateTimeImmutable $hasta): void
    {
        if ($hasta < $desde) {
            throw new DomainException('El periodo del reporte no puede terminar antes de empezar');
        }
    }
}
