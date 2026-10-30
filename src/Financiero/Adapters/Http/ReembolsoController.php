<?php

declare(strict_types=1);

namespace Src\Financiero\Adapters\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Financiero\Adapters\Presenter\FinancieroPresentador;
use Src\Financiero\Application\UseCase\GestionarReembolsos\AprobarReembolsoRequest;
use Src\Financiero\Application\UseCase\GestionarReembolsos\GestionarReembolsosUseCase;
use Src\Financiero\Application\UseCase\GestionarReembolsos\ProcesarReembolsoRequest;
use Src\Financiero\Application\UseCase\GestionarReembolsos\SolicitarReembolsoRequest;
use Src\Financiero\Domain\Enum\MotivoReembolso;
use Src\Shared\Adapters\Http\ControladorBase;
use Src\Shared\Adapters\Presenter\PresentadorBase;
use Src\Shared\Domain\Exception\OperacionNoPermitida;
use Src\Shared\Domain\Money;

/** . */
final class ReembolsoController extends ControladorBase
{
    protected function presentador(): PresentadorBase
    {
        return new FinancieroPresentador;
    }

    public function __construct(
        private readonly GestionarReembolsosUseCase $gestionarReembolsos,
    ) {}

    public function solicitar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'reserva_id' => 'required|uuid',
            'motivo' => 'nullable|string',
            'fecha_cancelacion' => 'nullable|date',
            'monto_forzado_centavos' => 'nullable|integer|min:1',
            'moneda' => 'nullable|string|size:3',
            'detalle' => 'nullable|string|max:500',
        ]);

        // Forzar un monto es una decision administrativa, no del cliente.
        if (isset($d['monto_forzado_centavos']) && ! $this->esAdmin($request)) {
            throw new OperacionNoPermitida('Solo un ADMIN puede forzar el monto del reembolso');
        }

        $reembolso = $this->gestionarReembolsos->solicitar(new SolicitarReembolsoRequest(
            reservaId: $d['reserva_id'],
            motivo: $this->enum(MotivoReembolso::class, $d['motivo'] ?? null) ?? MotivoReembolso::CANCELACION_CLIENTE,
            fechaCancelacion: $this->fecha($d['fecha_cancelacion'] ?? null),
            montoForzado: isset($d['monto_forzado_centavos'])
                ? Money::deCentavos($d['monto_forzado_centavos'], $d['moneda'] ?? 'BOB')
                : null,
            detalle: $d['detalle'] ?? null,
            solicitadoPor: $this->sesion($request)->usuarioId,
        ));

        // null = la politica dio 0 %: no hay nada que reembolsar.
        return $reembolso === null
            ? new JsonResponse(['data' => null], 200)
            : $this->responder($reembolso, 201);
    }

    public function revisar(Request $request, string $reembolsoId): JsonResponse
    {
        $d = $request->validate([
            'aprobado' => 'required|boolean',
            'detalle' => 'nullable|string|max:500',
        ]);

        return $this->responder($this->gestionarReembolsos->revisar(new AprobarReembolsoRequest(
            reembolsoId: $reembolsoId,
            aprobado: (bool) $d['aprobado'],
            revisadoPor: $this->sesion($request)->usuarioId,
            detalle: $d['detalle'] ?? null,
        )));
    }

    public function procesar(Request $request, string $reembolsoId): JsonResponse
    {
        return $this->responder($this->gestionarReembolsos->procesar(new ProcesarReembolsoRequest(
            reembolsoId: $reembolsoId,
            idempotencyKey: $request->header('Idempotency-Key'),
        )));
    }
}
