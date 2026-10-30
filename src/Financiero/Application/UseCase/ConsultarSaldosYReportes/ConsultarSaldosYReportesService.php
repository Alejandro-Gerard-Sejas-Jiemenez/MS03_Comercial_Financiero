<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ConsultarSaldosYReportes;

use DateTimeImmutable;
use Src\Financiero\Application\Port\ComisionRepository;
use Src\Financiero\Application\Port\ConsultaDeReportes;
use Src\Financiero\Application\Port\IngresoPorCategoria;
use Src\Financiero\Application\Port\LibroContableRepository;
use Src\Financiero\Application\Port\LiquidacionRepository;
use Src\Financiero\Application\Response\ComisionResponse;
use Src\Financiero\Application\Response\DetalleDeLiquidacionResponse;
use Src\Financiero\Application\Response\IngresoPorCategoriaResponse;
use Src\Financiero\Application\Response\LiquidacionResponse;
use Src\Financiero\Application\Response\ResumenDeIngresosResponse;
use Src\Financiero\Domain\Entity\Liquidacion;
use Src\Financiero\Domain\Enum\EstadoLiquidacion;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Response\MontoResponse;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/** Caso de uso ConsultarSaldosYReportes: implementa la interfaz de entrada ConsultarSaldosYReportesUseCase. */
final class ConsultarSaldosYReportesService implements ConsultarSaldosYReportesUseCase
{
    public function __construct(
        private readonly LiquidacionRepository $liquidaciones,
        private readonly ComisionRepository $comisiones,
        private readonly LibroContableRepository $libro,
        private readonly Clock $clock,
        private readonly ConsultaDeReportes $consulta,
    ) {}

    /** @return list<Liquidacion> */
    public function historialDe(
        string $anfitrionId,
        ?EstadoLiquidacion $estado = null,
        int $limite = 50,
    ): array {
        return array_map(LiquidacionResponse::desde(...), $this->liquidaciones->deAnfitrion($anfitrionId, $estado, $limite));
    }

    /** @return list<LiquidacionResponse> */
    public function todas(?EstadoLiquidacion $estado = null, ?string $anfitrionId = null, int $limite = 100): array
    {
        return array_map(LiquidacionResponse::desde(...), $this->liquidaciones->todas($estado, $anfitrionId, $limite));
    }

    public function detalle(string $liquidacionId): DetalleDeLiquidacionResponse
    {
        $liquidacion = $this->liquidaciones->buscar($liquidacionId)
            ?? throw new RecursoNoEncontrado("La liquidacion {$liquidacionId} no existe");

        return DetalleDeLiquidacionResponse::desde(new DetalleDeLiquidacion(
            liquidacion: $liquidacion,
            comisiones: $this->comisiones->deLiquidacion($liquidacionId),
        ));
    }

    /** Lo que la plataforma le debe hoy al anfitrion. */
    public function saldoDe(string $anfitrionId, string $moneda = 'BOB'): MontoResponse
    {
        return MontoResponse::desde($this->libro->saldoPorPagarDe($anfitrionId, $moneda));
    }

    /**
     * Comisiones ya liquidables del anfitrion: lo que entraria en la proxima
     * corrida de .
     *
     * @return list<ComisionResponse>
     */
    public function porLiquidarDe(string $anfitrionId, ?DateTimeImmutable $hasta = null): array
    {
        return array_map(ComisionResponse::desde(...), $this->comisiones->liquidablesDe($anfitrionId, $hasta ?? $this->clock->hoy()));
    }

    public function ingresos(
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
        string $moneda = 'BOB',
    ): ResumenDeIngresosResponse {
        $this->validarPeriodo($desde, $hasta);

        return ResumenDeIngresosResponse::desde($this->consulta->ingresosPorPeriodo($desde, $hasta, $moneda));
    }

    /** @return list<IngresoPorCategoria> */
    public function porCategoria(
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
        string $moneda = 'BOB',
    ): array {
        $this->validarPeriodo($desde, $hasta);

        return array_map(IngresoPorCategoriaResponse::desde(...), $this->consulta->ingresosPorCategoria($desde, $hasta, $moneda));
    }

    private function validarPeriodo(DateTimeImmutable $desde, DateTimeImmutable $hasta): void
    {
        if ($hasta < $desde) {
            throw new ReglaDeNegocioViolada('El periodo del reporte no puede terminar antes de empezar');
        }
    }
}
