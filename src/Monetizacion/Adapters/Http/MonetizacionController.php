<?php

declare(strict_types=1);

namespace Src\Monetizacion\Adapters\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Monetizacion\Adapters\Presenter\MonetizacionPresentador;
use Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial\CalcularDesgloseComercialRequest;
use Src\Monetizacion\Application\UseCase\CalcularDesgloseComercial\CalcularDesgloseComercialUseCase;
use Src\Monetizacion\Application\UseCase\GestionarReglasMonetizacion\ActualizarReglaRequest;
use Src\Monetizacion\Application\UseCase\GestionarReglasMonetizacion\CrearReglaRequest;
use Src\Monetizacion\Application\UseCase\GestionarReglasMonetizacion\GestionarReglasMonetizacionUseCase;
use Src\Monetizacion\Domain\Enum\Concepto;
use Src\Monetizacion\Domain\Enum\EstadoRegla;
use Src\Monetizacion\Domain\Enum\FinanciamientoDescuento;
use Src\Monetizacion\Domain\Enum\TipoCalculo;
use Src\Shared\Adapters\Http\ControladorBase;
use Src\Shared\Adapters\Presenter\PresentadorBase;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/** (reglas de comision y cargos, solo ADMIN) y (desglose comercial). */
final class MonetizacionController extends ControladorBase
{
    protected function presentador(): PresentadorBase
    {
        return new MonetizacionPresentador;
    }

    public function __construct(
        private readonly GestionarReglasMonetizacionUseCase $reglas,
        private readonly CalcularDesgloseComercialUseCase $desglose,
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

        return $this->responder($this->reglas->crear(new CrearReglaRequest(
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

    /** PUT: actualiza lo que se envia. `estado: INACTIVA` desactiva la regla y viaja solo. */
    public function actualizar(Request $request, string $reglaId): JsonResponse
    {
        $d = $request->validate([
            'nombre' => 'nullable|string|max:100',
            'porcentaje' => 'nullable|numeric',
            'monto_fijo_centavos' => 'nullable|integer|min:0',
            'vigente_hasta' => 'nullable|date',
            'estado' => 'nullable|string',
        ]);
        $yo = $this->sesion($request)->usuarioId;

        if (isset($d['estado'])) {
            if (array_diff(array_keys(array_filter($d, static fn (mixed $v): bool => $v !== null)), ['estado']) !== []) {
                throw new ReglaDeNegocioViolada('El estado se cambia solo: no lo mandes junto con otros campos');
            }
            if ($this->enumObligatorio(EstadoRegla::class, $d['estado']) !== EstadoRegla::INACTIVA) {
                throw new ReglaDeNegocioViolada('Solo se puede cambiar el estado a INACTIVA');
            }

            return $this->responder($this->reglas->desactivar($reglaId, $yo));
        }

        return $this->responder($this->reglas->actualizar(new ActualizarReglaRequest(
            reglaId: $reglaId,
            actualizadoPor: $yo,
            nombre: $d['nombre'] ?? null,
            porcentaje: isset($d['porcentaje']) ? (string) $d['porcentaje'] : null,
            montoFijoCentavos: $d['monto_fijo_centavos'] ?? null,
            vigenteHasta: $this->fecha($d['vigente_hasta'] ?? null),
        )));
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

        return $this->responder($this->desglose->calcular(new CalcularDesgloseComercialRequest(
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
