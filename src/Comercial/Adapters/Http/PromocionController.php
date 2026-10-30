<?php

declare(strict_types=1);

namespace Src\Comercial\Adapters\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Comercial\Adapters\Presenter\ComercialPresentador;
use Src\Comercial\Application\Port\CalculadorDeDescuento;
use Src\Comercial\Application\Port\SolicitudDescuento;
use Src\Comercial\Application\UseCase\GestionarPromociones\ActualizarPromocionRequest;
use Src\Comercial\Application\UseCase\GestionarPromociones\AsignarAlojamientosRequest;
use Src\Comercial\Application\UseCase\GestionarPromociones\CrearPromocionRequest;
use Src\Comercial\Application\UseCase\GestionarPromociones\GestionarPromocionesUseCase;
use Src\Comercial\Domain\Enum\EstadoPromocion;
use Src\Comercial\Domain\Enum\RolCreador;
use Src\Comercial\Domain\Enum\TipoDescuento;
use Src\Comercial\Domain\Enum\TipoPromocion;
use Src\Monetizacion\Domain\Enum\FinanciamientoDescuento;
use Src\Shared\Adapters\Http\ControladorBase;
use Src\Shared\Adapters\Presenter\PresentadorBase;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/** (gestion) y (validar cupon / calcular descuento). */
final class PromocionController extends ControladorBase
{
    protected function presentador(): PresentadorBase
    {
        return new ComercialPresentador;
    }

    public function __construct(
        private readonly GestionarPromocionesUseCase $promociones,
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
        if ($promocion->anfitrionId !== null) {
            $this->exigirPropietario($request, $promocion->anfitrionId);
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

        $promocion = $this->promociones->crear(new CrearPromocionRequest(
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

    /**
     * PUT: actualiza lo que se envia. El `estado` (ACTIVA, PAUSADA, CANCELADA) viaja solo;
     * los datos y `alojamientos` pueden ir juntos.
     */
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
            'alojamientos' => 'nullable|array',
            'alojamientos.*' => 'string',
            'estado' => 'nullable|string',
        ]);
        $this->exigirDuenoDeLaPromocion($request, $promocionId);
        $yo = $this->sesion($request)->usuarioId;
        $enviados = array_keys(array_filter($d, static fn (mixed $v): bool => $v !== null));

        if (isset($d['estado'])) {
            if (array_diff($enviados, ['estado']) !== []) {
                throw new ReglaDeNegocioViolada('El estado se cambia solo: no lo mandes junto con otros campos');
            }

            return $this->responder(match ($this->enumObligatorio(EstadoPromocion::class, $d['estado'])) {
                EstadoPromocion::ACTIVA => $this->promociones->activar($promocionId, $yo),
                EstadoPromocion::PAUSADA => $this->promociones->pausar($promocionId, $yo),
                EstadoPromocion::CANCELADA => $this->promociones->cancelar($promocionId, $yo),
                default => throw new ReglaDeNegocioViolada('El estado debe ser ACTIVA, PAUSADA o CANCELADA'),
            });
        }

        $resultado = null;
        if (array_diff($enviados, ['alojamientos']) !== []) {
            $resultado = $this->promociones->actualizar(new ActualizarPromocionRequest(
                promocionId: $promocionId,
                actualizadoPor: $yo,
                nombre: $d['nombre'] ?? null,
                valor: isset($d['valor']) ? (string) $d['valor'] : null,
                vigenteDesde: $this->fecha($d['vigente_desde'] ?? null),
                vigenteHasta: $this->fecha($d['vigente_hasta'] ?? null),
                minNoches: $d['min_noches'] ?? null,
                maxUsos: $d['max_usos'] ?? null,
                financiadaPor: $this->enum(FinanciamientoDescuento::class, $d['financiada_por'] ?? null),
            ));
        }
        if (isset($d['alojamientos'])) {
            $resultado = $this->promociones->asignarAlojamientos(new AsignarAlojamientosRequest($promocionId, $d['alojamientos'], $yo));
        }

        return $this->responder($resultado ?? $this->promociones->consultar($promocionId));
    }

    /** `data` es null si ninguna promocion aplica. */
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
        $this->exigirPropietario($request, $this->promociones->consultar($promocionId)->anfitrionId);
    }
}
