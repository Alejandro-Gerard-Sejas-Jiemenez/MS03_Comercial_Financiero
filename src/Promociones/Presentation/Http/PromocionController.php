<?php

declare(strict_types=1);

namespace Src\Promociones\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Monetizacion\Domain\FinanciamientoDescuento;
use Src\Promociones\Application\Port\CalculadorDeDescuento;
use Src\Promociones\Application\Port\SolicitudDescuento;
use Src\Promociones\Application\UseCase\GestionarPromociones\ActualizarPromocionCommand;
use Src\Promociones\Application\UseCase\GestionarPromociones\AsignarAlojamientosCommand;
use Src\Promociones\Application\UseCase\GestionarPromociones\CrearPromocionCommand;
use Src\Promociones\Application\UseCase\GestionarPromociones\GestionarPromocionesService;
use Src\Promociones\Domain\EstadoPromocion;
use Src\Promociones\Domain\RolCreador;
use Src\Promociones\Domain\TipoDescuento;
use Src\Promociones\Domain\TipoPromocion;
use Src\Shared\Presentation\Http\ControladorBase;

/** CU-PRO-01 (gestion) y CU-PRO-02 (validar cupon / calcular descuento). */
final class PromocionController extends ControladorBase
{
    public function __construct(
        private readonly GestionarPromocionesService $promociones,
        private readonly CalculadorDeDescuento $calculador,
    ) {}

    public function listar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'anfitrion_id' => 'nullable|uuid',
            'estado' => 'nullable|string',
            'tipo' => 'nullable|string',
        ]);

        // El ANFITRION solo lista las suyas.
        $anfitrionId = $this->esAdmin($request) ? ($d['anfitrion_id'] ?? null) : $this->sesion($request)->usuarioId;

        return $this->responder($this->promociones->listar(
            $anfitrionId,
            $this->enum(EstadoPromocion::class, $d['estado'] ?? null),
            $this->enum(TipoPromocion::class, $d['tipo'] ?? null),
        ));
    }

    public function mostrar(Request $request, string $promocionId): JsonResponse
    {
        $promocion = $this->promociones->consultar($promocionId);
        // Las de la plataforma (sin anfitrion) las ve cualquiera; las de un anfitrion, su dueno.
        if ($promocion->anfitrionId() !== null) {
            $this->exigirPropietario($request, $promocion->anfitrionId());
        }

        return $this->responder($promocion);
    }

    public function crear(Request $request): JsonResponse
    {
        $d = $request->validate([
            'nombre' => 'required|string|max:100',
            'tipo' => 'required|string',
            'tipo_descuento' => 'required|string',
            'valor' => 'required|numeric',
            'vigente_desde' => 'required|date',
            'vigente_hasta' => 'required|date',
            'codigo' => 'nullable|string|max:30',
            'anfitrion_id' => 'nullable|uuid',
            'financiada_por' => 'nullable|string',
            'min_noches' => 'nullable|integer|min:1',
            'max_usos' => 'nullable|integer|min:1',
            'moneda' => 'nullable|string|size:3',
            'alojamientos' => 'nullable|array',
            'alojamientos.*' => 'string',
        ]);

        $admin = $this->esAdmin($request);
        $yo = $this->sesion($request)->usuarioId;

        $promocion = $this->promociones->crear(new CrearPromocionCommand(
            nombre: $d['nombre'],
            tipo: $this->enumObligatorio(TipoPromocion::class, $d['tipo']),
            tipoDescuento: $this->enumObligatorio(TipoDescuento::class, $d['tipo_descuento']),
            valor: (string) $d['valor'],
            vigenteDesde: $this->fecha($d['vigente_desde']),
            vigenteHasta: $this->fecha($d['vigente_hasta']),
            creadaPorRol: $admin ? RolCreador::ADMIN : RolCreador::ANFITRION,
            creadaPor: $yo,
            codigo: $d['codigo'] ?? null,
            // Un anfitrion solo crea promociones propias; el ADMIN puede dejarla global.
            anfitrionId: $admin ? ($d['anfitrion_id'] ?? null) : $yo,
            financiadaPor: $this->enum(FinanciamientoDescuento::class, $d['financiada_por'] ?? null)
                ?? FinanciamientoDescuento::ANFITRION,
            minNoches: $d['min_noches'] ?? 1,
            maxUsos: $d['max_usos'] ?? null,
            moneda: $d['moneda'] ?? 'BOB',
            alojamientos: $d['alojamientos'] ?? [],
        ));

        return $this->responder($promocion, 201);
    }

    public function actualizar(Request $request, string $promocionId): JsonResponse
    {
        $d = $request->validate([
            'nombre' => 'nullable|string|max:100',
            'valor' => 'nullable|numeric',
            'vigente_desde' => 'nullable|date',
            'vigente_hasta' => 'nullable|date',
            'min_noches' => 'nullable|integer|min:1',
            'max_usos' => 'nullable|integer|min:1',
            'financiada_por' => 'nullable|string',
        ]);
        $this->exigirDuenoDeLaPromocion($request, $promocionId);

        return $this->responder($this->promociones->actualizar(new ActualizarPromocionCommand(
            promocionId: $promocionId,
            actualizadoPor: $this->sesion($request)->usuarioId,
            nombre: $d['nombre'] ?? null,
            valor: isset($d['valor']) ? (string) $d['valor'] : null,
            vigenteDesde: $this->fecha($d['vigente_desde'] ?? null),
            vigenteHasta: $this->fecha($d['vigente_hasta'] ?? null),
            minNoches: $d['min_noches'] ?? null,
            maxUsos: $d['max_usos'] ?? null,
            financiadaPor: $this->enum(FinanciamientoDescuento::class, $d['financiada_por'] ?? null),
        )));
    }

    public function asignarAlojamientos(Request $request, string $promocionId): JsonResponse
    {
        $d = $request->validate(['alojamientos' => 'present|array', 'alojamientos.*' => 'string']);
        $this->exigirDuenoDeLaPromocion($request, $promocionId);

        return $this->responder($this->promociones->asignarAlojamientos(new AsignarAlojamientosCommand(
            $promocionId,
            $d['alojamientos'],
            $this->sesion($request)->usuarioId,
        )));
    }

    public function activar(Request $request, string $promocionId): JsonResponse
    {
        $this->exigirDuenoDeLaPromocion($request, $promocionId);

        return $this->responder($this->promociones->activar($promocionId, $this->sesion($request)->usuarioId));
    }

    public function pausar(Request $request, string $promocionId): JsonResponse
    {
        $this->exigirDuenoDeLaPromocion($request, $promocionId);

        return $this->responder($this->promociones->pausar($promocionId, $this->sesion($request)->usuarioId));
    }

    public function cancelar(Request $request, string $promocionId): JsonResponse
    {
        $this->exigirDuenoDeLaPromocion($request, $promocionId);

        return $this->responder($this->promociones->cancelar($promocionId, $this->sesion($request)->usuarioId));
    }

    /** CU-PRO-02: `data` es null si ninguna promocion aplica. */
    public function descuento(Request $request): JsonResponse
    {
        $d = $request->validate([
            'subtotal_centavos' => 'required|integer|min:0',
            'noches' => 'required|integer|min:1',
            'moneda' => 'nullable|string|size:3',
            'codigo_cupon' => 'nullable|string',
            'promocion_id' => 'nullable|uuid',
            'alojamiento_id' => 'nullable|string',
            'anfitrion_id' => 'nullable|uuid',
            'fecha' => 'nullable|date',
        ]);

        return $this->responder($this->calculador->descuentoAplicable(new SolicitudDescuento(
            subtotalCentavos: $d['subtotal_centavos'],
            noches: $d['noches'],
            moneda: $d['moneda'] ?? 'BOB',
            codigoCupon: $d['codigo_cupon'] ?? null,
            promocionId: $d['promocion_id'] ?? null,
            alojamientoId: $d['alojamiento_id'] ?? null,
            anfitrionId: $d['anfitrion_id'] ?? null,
            fecha: $this->fecha($d['fecha'] ?? null),
        )));
    }

    private function exigirDuenoDeLaPromocion(Request $request, string $promocionId): void
    {
        $this->exigirPropietario($request, $this->promociones->consultar($promocionId)->anfitrionId());
    }
}
