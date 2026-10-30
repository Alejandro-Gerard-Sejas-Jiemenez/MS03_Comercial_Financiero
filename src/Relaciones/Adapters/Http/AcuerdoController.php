<?php

declare(strict_types=1);

namespace Src\Relaciones\Adapters\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Relaciones\Adapters\Presenter\RelacionesPresentador;
use Src\Relaciones\Application\UseCase\GestionarAcuerdosComerciales\ActualizarCondicionesAcuerdoRequest;
use Src\Relaciones\Application\UseCase\GestionarAcuerdosComerciales\CambiarEstadoAcuerdoRequest;
use Src\Relaciones\Application\UseCase\GestionarAcuerdosComerciales\CrearAcuerdoBorradorRequest;
use Src\Relaciones\Application\UseCase\GestionarAcuerdosComerciales\GestionarAcuerdosComercialesUseCase;
use Src\Relaciones\Domain\Enum\EstadoAcuerdo;
use Src\Shared\Adapters\Http\ControladorBase;
use Src\Shared\Adapters\Presenter\PresentadorBase;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/** (gestion) y (cambio de estado). Los escribe el ADMIN; el anfitrion lee los suyos. */
final class AcuerdoController extends ControladorBase
{
    protected function presentador(): PresentadorBase
    {
        return new RelacionesPresentador;
    }

    public function __construct(
        private readonly GestionarAcuerdosComercialesUseCase $gestionarAcuerdosComerciales,
    ) {}

    public function listar(Request $request): JsonResponse
    {
        $d = $request->validate(['estado' => 'nullable|string', 'anfitrion_id' => 'nullable|uuid']);
        $anfitrionId = $this->esAdmin($request) ? ($d['anfitrion_id'] ?? null) : $this->sesion($request)->usuarioId;

        return $this->responder($this->gestionarAcuerdosComerciales->listar(
            $this->enum(EstadoAcuerdo::class, $d['estado'] ?? null),
            $anfitrionId,
        ));
    }

    public function mostrar(Request $request, string $acuerdoId): JsonResponse
    {
        $acuerdo = $this->gestionarAcuerdosComerciales->consultar($acuerdoId);
        $this->exigirPropietario($request, $acuerdo->anfitrionId);

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

        return $this->responder($this->gestionarAcuerdosComerciales->crear(new CrearAcuerdoBorradorRequest(
            anfitrionId: $d['anfitrion_id'],
            fechaInicio: $this->fecha($d['fecha_inicio']),
            creadoPor: $this->sesion($request)->usuarioId,
            diasLiquidacion: $d['dias_liquidacion'] ?? 7,
            reglaComisionId: $d['regla_comision_id'] ?? null,
            fechaFin: $this->fecha($d['fecha_fin'] ?? null),
            condiciones: $d['condiciones'] ?? [],
        )), 201);
    }

    /** PUT: o cambia el `estado` (solo, con su motivo), o actualiza las condiciones que se envian. */
    public function actualizar(Request $request, string $acuerdoId): JsonResponse
    {
        $d = $request->validate([
            'estado' => 'nullable|string',
            'motivo' => 'nullable|string|max:500',
            'saga_id' => 'nullable|uuid',
            'dias_liquidacion' => 'nullable|integer',
            'regla_comision_id' => 'nullable|uuid',
            'condiciones' => 'nullable|array',
            'fecha_fin' => 'nullable|date',
        ]);
        $yo = $this->sesion($request)->usuarioId;

        if (isset($d['estado'])) {
            $otros = array_diff(array_keys(array_filter($d, static fn (mixed $v): bool => $v !== null)), ['estado', 'motivo', 'saga_id']);
            if ($otros !== []) {
                throw new ReglaDeNegocioViolada('El estado se cambia solo: no lo mandes junto con condiciones');
            }

            return $this->responder($this->gestionarAcuerdosComerciales->cambiarEstado(new CambiarEstadoAcuerdoRequest(
                acuerdoId: $acuerdoId,
                estadoDestino: $this->enumObligatorio(EstadoAcuerdo::class, $d['estado']),
                solicitadoPor: $yo,
                motivo: $d['motivo'] ?? null,
                sagaId: $d['saga_id'] ?? null,
            )));
        }

        return $this->responder($this->gestionarAcuerdosComerciales->actualizarCondiciones(new ActualizarCondicionesAcuerdoRequest(
            acuerdoId: $acuerdoId,
            actualizadoPor: $yo,
            diasLiquidacion: $d['dias_liquidacion'] ?? null,
            reglaComisionId: $d['regla_comision_id'] ?? null,
            condiciones: $d['condiciones'] ?? null,
            fechaFin: $this->fecha($d['fecha_fin'] ?? null),
        )));
    }
}
