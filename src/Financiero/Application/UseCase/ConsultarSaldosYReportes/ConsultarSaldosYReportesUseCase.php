<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ConsultarSaldosYReportes;

use DateTimeImmutable;
use Src\Financiero\Application\Response\ComisionResponse;
use Src\Financiero\Application\Response\DetalleDeLiquidacionResponse;
use Src\Financiero\Application\Response\IngresoPorCategoriaResponse;
use Src\Financiero\Application\Response\LiquidacionResponse;
use Src\Financiero\Application\Response\ResumenDeIngresosResponse;
use Src\Financiero\Domain\Enum\EstadoLiquidacion;
use Src\Shared\Application\Response\MontoResponse;

/**
 * Entrada del caso de uso ConsultarSaldosYReportes (Clean Architecture: Input Boundary).
 * Los adaptadores (controladores, consolas, suscriptores) dependen de esta interfaz, no del servicio.
 */
interface ConsultarSaldosYReportesUseCase
{
    /** @return list<LiquidacionResponse> */
    public function historialDe(string $anfitrionId, ?EstadoLiquidacion $estado = null, int $limite = 50): array;

    /** @return list<LiquidacionResponse> */
    public function todas(?EstadoLiquidacion $estado = null, ?string $anfitrionId = null, int $limite = 100): array;

    public function detalle(string $liquidacionId): DetalleDeLiquidacionResponse;

    public function saldoDe(string $anfitrionId, string $moneda = 'BOB'): MontoResponse;

    /** @return list<ComisionResponse> */
    public function porLiquidarDe(string $anfitrionId, ?DateTimeImmutable $hasta = null): array;

    public function ingresos(DateTimeImmutable $desde, DateTimeImmutable $hasta, string $moneda = 'BOB'): ResumenDeIngresosResponse;

    /** @return list<IngresoPorCategoriaResponse> */
    public function porCategoria(DateTimeImmutable $desde, DateTimeImmutable $hasta, string $moneda = 'BOB'): array;
}
