<?php

declare(strict_types=1);

namespace Src\Pagos\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Pagos\Application\UseCase\AprobarReembolso\AprobarReembolsoCommand;
use Src\Pagos\Application\UseCase\AprobarReembolso\AprobarReembolsoHandler;
use Src\Pagos\Application\UseCase\ProcesarReembolso\ProcesarReembolsoCommand;
use Src\Pagos\Application\UseCase\ProcesarReembolso\ProcesarReembolsoHandler;
use Src\Pagos\Application\UseCase\SolicitarReembolso\SolicitarReembolsoCommand;
use Src\Pagos\Application\UseCase\SolicitarReembolso\SolicitarReembolsoHandler;
use Src\Pagos\Domain\MotivoReembolso;
use Src\Shared\Domain\Exception\OperacionNoPermitida;
use Src\Shared\Domain\Money;
use Src\Shared\Presentation\Http\ControladorBase;

/** CU-PAG-07, 08 y 09. */
final class ReembolsoController extends ControladorBase
{
    public function __construct(
        private readonly SolicitarReembolsoHandler $solicitar,
        private readonly AprobarReembolsoHandler $aprobar,
        private readonly ProcesarReembolsoHandler $procesar,
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

        $reembolso = ($this->solicitar)(new SolicitarReembolsoCommand(
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

        return $this->responder(($this->aprobar)(new AprobarReembolsoCommand(
            reembolsoId: $reembolsoId,
            aprobado: (bool) $d['aprobado'],
            revisadoPor: $this->sesion($request)->usuarioId,
            detalle: $d['detalle'] ?? null,
        )));
    }

    public function procesar(Request $request, string $reembolsoId): JsonResponse
    {
        return $this->responder(($this->procesar)(new ProcesarReembolsoCommand(
            reembolsoId: $reembolsoId,
            idempotencyKey: $request->header('Idempotency-Key'),
        )));
    }
}
