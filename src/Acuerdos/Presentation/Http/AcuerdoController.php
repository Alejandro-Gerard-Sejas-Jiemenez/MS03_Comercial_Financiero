<?php

declare(strict_types=1);

namespace Src\Acuerdos\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Acuerdos\Application\UseCase\CambiarEstadoAcuerdo\CambiarEstadoAcuerdoCommand;
use Src\Acuerdos\Application\UseCase\CambiarEstadoAcuerdo\CambiarEstadoAcuerdoHandler;
use Src\Acuerdos\Application\UseCase\GestionarAcuerdos\ActualizarCondicionesAcuerdoCommand;
use Src\Acuerdos\Application\UseCase\GestionarAcuerdos\CrearAcuerdoBorradorCommand;
use Src\Acuerdos\Application\UseCase\GestionarAcuerdos\GestionarAcuerdosService;
use Src\Acuerdos\Domain\EstadoAcuerdo;
use Src\Shared\Presentation\Http\ControladorBase;

/** CU-ACU-01 (gestion) y CU-ACU-02 (cambio de estado). Los escribe el ADMIN; el anfitrion lee los suyos. */
final class AcuerdoController extends ControladorBase
{
    public function __construct(
        private readonly GestionarAcuerdosService $acuerdos,
        private readonly CambiarEstadoAcuerdoHandler $cambiarEstado,
    ) {}

    public function listar(Request $request): JsonResponse
    {
        $d = $request->validate(['estado' => 'nullable|string', 'anfitrion_id' => 'nullable|uuid']);
        $anfitrionId = $this->esAdmin($request) ? ($d['anfitrion_id'] ?? null) : $this->sesion($request)->usuarioId;

        return $this->responder($this->acuerdos->listar(
            $this->enum(EstadoAcuerdo::class, $d['estado'] ?? null),
            $anfitrionId,
        ));
    }

    public function historial(Request $request, string $anfitrionId): JsonResponse
    {
        $this->exigirPropietario($request, $anfitrionId);

        return $this->responder($this->acuerdos->historialDe($anfitrionId));
    }

    public function mostrar(Request $request, string $acuerdoId): JsonResponse
    {
        $acuerdo = $this->acuerdos->consultar($acuerdoId);
        $this->exigirPropietario($request, $acuerdo->anfitrionId());

        return $this->responder($acuerdo);
    }

    public function crear(Request $request): JsonResponse
    {
        $d = $request->validate([
            'anfitrion_id' => 'required|uuid',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date',
            'dias_liquidacion' => 'nullable|integer',
            'regla_comision_id' => 'nullable|uuid',
            'condiciones' => 'nullable|array',
        ]);

        return $this->responder($this->acuerdos->crear(new CrearAcuerdoBorradorCommand(
            anfitrionId: $d['anfitrion_id'],
            fechaInicio: $this->fecha($d['fecha_inicio']),
            creadoPor: $this->sesion($request)->usuarioId,
            diasLiquidacion: $d['dias_liquidacion'] ?? 7,
            reglaComisionId: $d['regla_comision_id'] ?? null,
            fechaFin: $this->fecha($d['fecha_fin'] ?? null),
            condiciones: $d['condiciones'] ?? [],
        )), 201);
    }

    public function actualizar(Request $request, string $acuerdoId): JsonResponse
    {
        $d = $request->validate([
            'dias_liquidacion' => 'nullable|integer',
            'regla_comision_id' => 'nullable|uuid',
            'condiciones' => 'nullable|array',
            'fecha_fin' => 'nullable|date',
        ]);

        return $this->responder($this->acuerdos->actualizarCondiciones(new ActualizarCondicionesAcuerdoCommand(
            acuerdoId: $acuerdoId,
            actualizadoPor: $this->sesion($request)->usuarioId,
            diasLiquidacion: $d['dias_liquidacion'] ?? null,
            reglaComisionId: $d['regla_comision_id'] ?? null,
            condiciones: $d['condiciones'] ?? null,
            fechaFin: $this->fecha($d['fecha_fin'] ?? null),
        )));
    }

    public function cambiarEstado(Request $request, string $acuerdoId): JsonResponse
    {
        $d = $request->validate([
            'estado' => 'required|string',
            'motivo' => 'nullable|string|max:500',
            'saga_id' => 'nullable|uuid',
        ]);

        return $this->responder($this->cambiarEstado->manejar(new CambiarEstadoAcuerdoCommand(
            acuerdoId: $acuerdoId,
            estadoDestino: $this->enumObligatorio(EstadoAcuerdo::class, $d['estado']),
            solicitadoPor: $this->sesion($request)->usuarioId,
            motivo: $d['motivo'] ?? null,
            sagaId: $d['saga_id'] ?? null,
        )));
    }
}
