<?php

declare(strict_types=1);

namespace Src\Monetizacion\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial\CalcularDesgloseComercialCommand;
use Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial\CalcularDesgloseComercialHandler;
use Src\Monetizacion\Application\UseCase\GestionarReglasMonetizacion\ActualizarReglaCommand;
use Src\Monetizacion\Application\UseCase\GestionarReglasMonetizacion\CrearReglaCommand;
use Src\Monetizacion\Application\UseCase\GestionarReglasMonetizacion\GestionarReglasMonetizacionService;
use Src\Monetizacion\Domain\Concepto;
use Src\Monetizacion\Domain\EstadoRegla;
use Src\Monetizacion\Domain\FinanciamientoDescuento;
use Src\Monetizacion\Domain\TipoCalculo;
use Src\Shared\Presentation\Http\ControladorBase;

/** CU-MON-01 (reglas de comision y cargos, solo ADMIN) y CU-MON-02 (desglose comercial). */
final class MonetizacionController extends ControladorBase
{
    public function __construct(
        private readonly GestionarReglasMonetizacionService $reglas,
        private readonly CalcularDesgloseComercialHandler $desglose,
    ) {}

    public function listar(Request $request): JsonResponse
    {
        $d = $request->validate(['concepto' => 'nullable|string', 'estado' => 'nullable|string']);

        return $this->responder($this->reglas->listar(
            $this->enum(Concepto::class, $d['concepto'] ?? null),
            $this->enum(EstadoRegla::class, $d['estado'] ?? null),
        ));
    }

    public function vigentes(Request $request): JsonResponse
    {
        $d = $request->validate([
            'concepto' => 'required|string',
            'fecha' => 'nullable|date',
            'categoria_alojamiento' => 'nullable|string',
            'nivel_anfitrion' => 'nullable|string',
        ]);

        return $this->responder($this->reglas->vigentes(
            $this->enumObligatorio(Concepto::class, $d['concepto']),
            $this->fecha($d['fecha'] ?? null),
            $d['categoria_alojamiento'] ?? null,
            $d['nivel_anfitrion'] ?? null,
        ));
    }

    public function mostrar(string $reglaId): JsonResponse
    {
        return $this->responder($this->reglas->consultar($reglaId));
    }

    public function crear(Request $request): JsonResponse
    {
        $d = $request->validate([
            'nombre' => 'required|string|max:100',
            'concepto' => 'required|string',
            'tipo_calculo' => 'required|string',
            'vigente_desde' => 'required|date',
            'vigente_hasta' => 'nullable|date',
            'porcentaje' => 'nullable|numeric',
            'monto_fijo_centavos' => 'nullable|integer|min:0',
            'moneda' => 'nullable|string|size:3',
            'categoria_alojamiento' => 'nullable|string',
            'nivel_anfitrion' => 'nullable|string',
        ]);

        return $this->responder($this->reglas->crear(new CrearReglaCommand(
            nombre: $d['nombre'],
            concepto: $this->enumObligatorio(Concepto::class, $d['concepto']),
            tipoCalculo: $this->enumObligatorio(TipoCalculo::class, $d['tipo_calculo']),
            vigenteDesde: $this->fecha($d['vigente_desde']),
            creadoPor: $this->sesion($request)->usuarioId,
            porcentaje: isset($d['porcentaje']) ? (string) $d['porcentaje'] : null,
            montoFijoCentavos: $d['monto_fijo_centavos'] ?? null,
            moneda: $d['moneda'] ?? 'BOB',
            vigenteHasta: $this->fecha($d['vigente_hasta'] ?? null),
            categoriaAlojamiento: $d['categoria_alojamiento'] ?? null,
            nivelAnfitrion: $d['nivel_anfitrion'] ?? null,
        )), 201);
    }

    public function actualizar(Request $request, string $reglaId): JsonResponse
    {
        $d = $request->validate([
            'nombre' => 'nullable|string|max:100',
            'porcentaje' => 'nullable|numeric',
            'monto_fijo_centavos' => 'nullable|integer|min:0',
            'vigente_hasta' => 'nullable|date',
        ]);

        return $this->responder($this->reglas->actualizar(new ActualizarReglaCommand(
            reglaId: $reglaId,
            actualizadoPor: $this->sesion($request)->usuarioId,
            nombre: $d['nombre'] ?? null,
            porcentaje: isset($d['porcentaje']) ? (string) $d['porcentaje'] : null,
            montoFijoCentavos: $d['monto_fijo_centavos'] ?? null,
            vigenteHasta: $this->fecha($d['vigente_hasta'] ?? null),
        )));
    }

    public function desactivar(Request $request, string $reglaId): JsonResponse
    {
        return $this->responder($this->reglas->desactivar($reglaId, $this->sesion($request)->usuarioId));
    }

    public function calcularDesglose(Request $request): JsonResponse
    {
        $d = $request->validate([
            'subtotal_centavos' => 'required|integer|min:0',
            'descuento_centavos' => 'nullable|integer|min:0',
            'moneda' => 'nullable|string|size:3',
            'financiamiento' => 'nullable|string',
            'categoria_alojamiento' => 'nullable|string',
            'nivel_anfitrion' => 'nullable|string',
            'regla_comision_negociada_id' => 'nullable|uuid',
            'fecha' => 'nullable|date',
        ]);

        return $this->responder(($this->desglose)(new CalcularDesgloseComercialCommand(
            subtotalCentavos: $d['subtotal_centavos'],
            descuentoCentavos: $d['descuento_centavos'] ?? 0,
            moneda: $d['moneda'] ?? 'BOB',
            financiamiento: $this->enum(FinanciamientoDescuento::class, $d['financiamiento'] ?? null)
                ?? FinanciamientoDescuento::ANFITRION,
            categoriaAlojamiento: $d['categoria_alojamiento'] ?? null,
            nivelAnfitrion: $d['nivel_anfitrion'] ?? null,
            reglaComisionNegociadaId: $d['regla_comision_negociada_id'] ?? null,
            fecha: $this->fecha($d['fecha'] ?? null),
        )));
    }
}
