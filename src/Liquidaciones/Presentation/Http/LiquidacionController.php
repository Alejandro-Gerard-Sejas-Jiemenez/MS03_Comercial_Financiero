<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Liquidaciones\Application\UseCase\AprobarLiquidacion\AprobarLiquidacionCommand;
use Src\Liquidaciones\Application\UseCase\AprobarLiquidacion\AprobarLiquidacionHandler;
use Src\Liquidaciones\Application\UseCase\ConsultarLiquidacionesYSaldos\ConsultarLiquidacionesYSaldosService;
use Src\Liquidaciones\Application\UseCase\PagarLiquidacion\PagarLiquidacionCommand;
use Src\Liquidaciones\Application\UseCase\PagarLiquidacion\PagarLiquidacionHandler;
use Src\Liquidaciones\Application\UseCase\RechazarLiquidacion\RechazarLiquidacionCommand;
use Src\Liquidaciones\Application\UseCase\RechazarLiquidacion\RechazarLiquidacionHandler;
use Src\Liquidaciones\Application\UseCase\ReportesFinancieros\ReportesFinancierosService;
use Src\Liquidaciones\Domain\EstadoLiquidacion;
use Src\Shared\Presentation\Http\ControladorBase;

/**
 * CU-LIQ-05, 06, 07 (el ADMIN decide y paga), CU-LIQ-09 (el anfitrion consulta
 * lo suyo) y CU-LIQ-10 (reportes, solo ADMIN).
 */
final class LiquidacionController extends ControladorBase
{
    public function __construct(
        private readonly AprobarLiquidacionHandler $aprobar,
        private readonly RechazarLiquidacionHandler $rechazar,
        private readonly PagarLiquidacionHandler $pagar,
        private readonly ConsultarLiquidacionesYSaldosService $consultas,
        private readonly ReportesFinancierosService $reportes,
    ) {}

    public function aprobar(Request $request, string $liquidacionId): JsonResponse
    {
        return $this->responder(($this->aprobar)(new AprobarLiquidacionCommand(
            $liquidacionId,
            $this->sesion($request)->usuarioId,
        )));
    }

    public function rechazar(Request $request, string $liquidacionId): JsonResponse
    {
        $d = $request->validate(['motivo' => 'required|string|max:500', 'anular' => 'nullable|boolean']);

        return $this->responder(($this->rechazar)(new RechazarLiquidacionCommand(
            liquidacionId: $liquidacionId,
            revisadoPor: $this->sesion($request)->usuarioId,
            motivo: $d['motivo'],
            anular: (bool) ($d['anular'] ?? false),
        )));
    }

    public function pagar(Request $request, string $liquidacionId): JsonResponse
    {
        $d = $request->validate(['referencia_transferencia' => 'required|string|max:100']);

        return $this->responder(($this->pagar)(new PagarLiquidacionCommand(
            $liquidacionId,
            $d['referencia_transferencia'],
            $this->sesion($request)->usuarioId,
        )));
    }

    public function historial(Request $request, string $anfitrionId): JsonResponse
    {
        $d = $request->validate(['estado' => 'nullable|string', 'limite' => 'nullable|integer|min:1|max:200']);
        $this->exigirPropietario($request, $anfitrionId);

        return $this->responder($this->consultas->historialDe(
            $anfitrionId,
            $this->enum(EstadoLiquidacion::class, $d['estado'] ?? null),
            $d['limite'] ?? 50,
        ));
    }

    public function detalle(Request $request, string $liquidacionId): JsonResponse
    {
        $detalle = $this->consultas->detalle($liquidacionId);
        $this->exigirPropietario($request, $detalle->liquidacion->anfitrionId());

        return $this->responder($detalle);
    }

    public function saldo(Request $request, string $anfitrionId): JsonResponse
    {
        $d = $request->validate(['moneda' => 'nullable|string|size:3']);
        $this->exigirPropietario($request, $anfitrionId);

        return $this->responder($this->consultas->saldoDe($anfitrionId, $d['moneda'] ?? 'BOB'));
    }

    public function porLiquidar(Request $request, string $anfitrionId): JsonResponse
    {
        $d = $request->validate(['hasta' => 'nullable|date']);
        $this->exigirPropietario($request, $anfitrionId);

        return $this->responder($this->consultas->porLiquidarDe($anfitrionId, $this->fecha($d['hasta'] ?? null)));
    }

    public function reporteIngresos(Request $request): JsonResponse
    {
        [$desde, $hasta, $moneda] = $this->periodo($request);

        return $this->responder($this->reportes->ingresos($desde, $hasta, $moneda));
    }

    public function reportePorCategoria(Request $request): JsonResponse
    {
        [$desde, $hasta, $moneda] = $this->periodo($request);

        return $this->responder($this->reportes->porCategoria($desde, $hasta, $moneda));
    }

    /** @return array{\DateTimeImmutable, \DateTimeImmutable, string} */
    private function periodo(Request $request): array
    {
        $d = $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date',
            'moneda' => 'nullable|string|size:3',
        ]);

        return [$this->fecha($d['desde']), $this->fecha($d['hasta']), $d['moneda'] ?? 'BOB'];
    }
}
