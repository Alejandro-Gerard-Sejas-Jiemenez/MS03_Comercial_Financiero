<?php

declare(strict_types=1);

namespace Src\Financiero\Adapters\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Financiero\Adapters\Presenter\FinancieroPresentador;
use Src\Financiero\Application\UseCase\ConsultarSaldosYReportes\ConsultarSaldosYReportesUseCase;
use Src\Financiero\Application\UseCase\GestionarLiquidaciones\AprobarLiquidacionRequest;
use Src\Financiero\Application\UseCase\GestionarLiquidaciones\GestionarLiquidacionesUseCase;
use Src\Financiero\Application\UseCase\GestionarLiquidaciones\PagarLiquidacionRequest;
use Src\Financiero\Application\UseCase\GestionarLiquidaciones\RechazarLiquidacionRequest;
use Src\Financiero\Domain\Enum\EstadoLiquidacion;
use Src\Shared\Adapters\Http\ControladorBase;
use Src\Shared\Adapters\Presenter\PresentadorBase;

/**
 * (el ADMIN decide y paga), (el anfitrion consulta
 * lo suyo) y (reportes, solo ADMIN).
 */
final class LiquidacionController extends ControladorBase
{
    protected function presentador(): PresentadorBase
    {
        return new FinancieroPresentador;
    }

    public function __construct(
        private readonly GestionarLiquidacionesUseCase $gestionarLiquidaciones,
        private readonly ConsultarSaldosYReportesUseCase $consultarSaldosYReportes,
    ) {}

    public function aprobar(Request $request, string $liquidacionId): JsonResponse
    {
        return $this->responder($this->gestionarLiquidaciones->aprobar(new AprobarLiquidacionRequest(
            $liquidacionId,
            $this->sesion($request)->usuarioId,
        )));
    }

    public function rechazar(Request $request, string $liquidacionId): JsonResponse
    {
        $d = $request->validate(['motivo' => 'required|string|max:500', 'anular' => 'nullable|boolean']);

        return $this->responder($this->gestionarLiquidaciones->rechazar(new RechazarLiquidacionRequest(
            liquidacionId: $liquidacionId,
            revisadoPor: $this->sesion($request)->usuarioId,
            motivo: $d['motivo'],
            anular: (bool) ($d['anular'] ?? false),
        )));
    }

    public function pagar(Request $request, string $liquidacionId): JsonResponse
    {
        $d = $request->validate(['referencia_transferencia' => 'required|string|max:100']);

        return $this->responder($this->gestionarLiquidaciones->pagar(new PagarLiquidacionRequest(
            $liquidacionId,
            $d['referencia_transferencia'],
            $this->sesion($request)->usuarioId,
        )));
    }

    public function listar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'estado' => 'nullable|string',
            'anfitrion_id' => 'nullable|uuid',
            'limite' => 'nullable|integer|min:1|max:200',
        ]);

        return $this->responder($this->consultarSaldosYReportes->todas(
            $this->enum(EstadoLiquidacion::class, $d['estado'] ?? null),
            $d['anfitrion_id'] ?? null,
            $d['limite'] ?? 100,
        ));
    }

    public function historial(Request $request, string $anfitrionId): JsonResponse
    {
        $d = $request->validate(['estado' => 'nullable|string', 'limite' => 'nullable|integer|min:1|max:200']);
        $this->exigirPropietario($request, $anfitrionId);

        return $this->responder($this->consultarSaldosYReportes->historialDe(
            $anfitrionId,
            $this->enum(EstadoLiquidacion::class, $d['estado'] ?? null),
            $d['limite'] ?? 50,
        ));
    }

    public function detalle(Request $request, string $liquidacionId): JsonResponse
    {
        $detalle = $this->consultarSaldosYReportes->detalle($liquidacionId);
        $this->exigirPropietario($request, $detalle->liquidacion->anfitrionId);

        return $this->responder($detalle);
    }

    public function saldo(Request $request, string $anfitrionId): JsonResponse
    {
        $d = $request->validate(['moneda' => 'nullable|string|size:3']);
        $this->exigirPropietario($request, $anfitrionId);

        return $this->responder($this->consultarSaldosYReportes->saldoDe($anfitrionId, $d['moneda'] ?? 'BOB'));
    }

    public function porLiquidar(Request $request, string $anfitrionId): JsonResponse
    {
        $d = $request->validate(['hasta' => 'nullable|date']);
        $this->exigirPropietario($request, $anfitrionId);

        return $this->responder($this->consultarSaldosYReportes->porLiquidarDe($anfitrionId, $this->fecha($d['hasta'] ?? null)));
    }

    public function reporteIngresos(Request $request): JsonResponse
    {
        [$desde, $hasta, $moneda] = $this->periodo($request);

        return $this->responder($this->consultarSaldosYReportes->ingresos($desde, $hasta, $moneda));
    }

    public function reportePorCategoria(Request $request): JsonResponse
    {
        [$desde, $hasta, $moneda] = $this->periodo($request);

        return $this->responder($this->consultarSaldosYReportes->porCategoria($desde, $hasta, $moneda));
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
