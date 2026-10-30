<?php

declare(strict_types=1);

namespace Src\Financiero\Adapters\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Financiero\Adapters\Presenter\FinancieroPresentador;
use Src\Financiero\Application\UseCase\ConciliarTransacciones\ConciliarTransaccionesUseCase;
use Src\Financiero\Application\UseCase\ConciliarTransacciones\EjecutarConciliacionRequest;
use Src\Financiero\Application\UseCase\ConciliarTransacciones\RevisarDiferenciaConciliacionRequest;
use Src\Shared\Adapters\Http\ControladorBase;
use Src\Shared\Adapters\Presenter\PresentadorBase;

/** . */
final class ConciliacionController extends ControladorBase
{
    protected function presentador(): PresentadorBase
    {
        return new FinancieroPresentador;
    }

    public function __construct(
        private readonly ConciliarTransaccionesUseCase $conciliarTransacciones,
    ) {}

    public function ejecutar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'fecha_corte' => 'nullable|date',
            'moneda' => 'nullable|string|size:3',
        ]);

        return $this->responder($this->conciliarTransacciones->ejecutar(new EjecutarConciliacionRequest(
            fechaCorte: $this->fecha($d['fecha_corte'] ?? null),
            ejecutadaPor: $this->sesion($request)->usuarioId,
            moneda: $d['moneda'] ?? 'BOB',
        )), 201);
    }

    public function revisar(Request $request, string $conciliacionId): JsonResponse
    {
        $d = $request->validate([
            'referencia_pasarela' => 'required|string',
            'observacion' => 'required|string|max:500',
            'transaccion_id' => 'nullable|string',
            'cerrar' => 'nullable|boolean',
        ]);

        return $this->responder($this->conciliarTransacciones->revisarDiferencia(new RevisarDiferenciaConciliacionRequest(
            conciliacionId: $conciliacionId,
            referenciaPasarela: $d['referencia_pasarela'],
            observacion: $d['observacion'],
            revisadoPor: $this->sesion($request)->usuarioId,
            transaccionId: $d['transaccion_id'] ?? null,
            cerrar: (bool) ($d['cerrar'] ?? false),
        )));
    }
}
