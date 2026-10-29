<?php

declare(strict_types=1);

namespace Src\Pagos\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Pagos\Application\UseCase\EjecutarConciliacion\EjecutarConciliacionCommand;
use Src\Pagos\Application\UseCase\EjecutarConciliacion\EjecutarConciliacionHandler;
use Src\Pagos\Application\UseCase\RevisarDiferenciaConciliacion\RevisarDiferenciaConciliacionCommand;
use Src\Pagos\Application\UseCase\RevisarDiferenciaConciliacion\RevisarDiferenciaConciliacionHandler;
use Src\Shared\Presentation\Http\ControladorBase;

/** CU-PAG-11 y 12. */
final class ConciliacionController extends ControladorBase
{
    public function __construct(
        private readonly EjecutarConciliacionHandler $ejecutar,
        private readonly RevisarDiferenciaConciliacionHandler $revisar,
    ) {}

    public function ejecutar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'fecha_corte' => 'nullable|date',
            'moneda' => 'nullable|string|size:3',
        ]);

        return $this->responder(($this->ejecutar)(new EjecutarConciliacionCommand(
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

        return $this->responder(($this->revisar)(new RevisarDiferenciaConciliacionCommand(
            conciliacionId: $conciliacionId,
            referenciaPasarela: $d['referencia_pasarela'],
            observacion: $d['observacion'],
            revisadoPor: $this->sesion($request)->usuarioId,
            transaccionId: $d['transaccion_id'] ?? null,
            cerrar: (bool) ($d['cerrar'] ?? false),
        )));
    }
}
