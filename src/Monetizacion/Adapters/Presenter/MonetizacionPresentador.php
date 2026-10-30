<?php

declare(strict_types=1);

namespace Src\Monetizacion\Adapters\Presenter;

use Src\Monetizacion\Application\Response\DesgloseComercialResponse;
use Src\Monetizacion\Application\Response\ReglaMonetizacionResponse;
use Src\Shared\Adapters\Presenter\PresentadorBase;

/** Presentador de Monetizacion: convierte los Response del caso de uso al arreglo que sale por HTTP. */
final class MonetizacionPresentador extends PresentadorBase
{
    protected function objeto(object $r): mixed
    {
        return match (true) {
            $r instanceof ReglaMonetizacionResponse => $this->reglaMonetizacion($r),
            $r instanceof DesgloseComercialResponse => $this->desgloseComercial($r),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function reglaMonetizacion(ReglaMonetizacionResponse $r): array
    {
        return [
            'regla_id' => $r->reglaId,
            'nombre' => $r->nombre,
            'concepto' => $r->concepto,
            'tipo_calculo' => $r->tipoCalculo,
            'porcentaje' => $r->porcentaje,
            'monto_fijo' => $this->monto($r->montoFijo),
            'moneda' => $r->moneda,
            'categoria_alojamiento' => $r->categoriaAlojamiento,
            'nivel_anfitrion' => $r->nivelAnfitrion,
            'vigente_desde' => $this->fecha($r->vigenteDesde),
            'vigente_hasta' => $this->fecha($r->vigenteHasta),
            'estado' => $r->estado,
            'creado_por' => $r->creadoPor,
        ];
    }

    /** @return array<string, mixed> */
    private function desgloseComercial(DesgloseComercialResponse $r): array
    {
        return [
            'subtotal' => $this->monto($r->subtotal),
            'descuento' => $this->monto($r->descuento),
            'cargo_servicio' => $this->monto($r->cargoServicio),
            'total' => $this->monto($r->total),
            'base_comisionable' => $this->monto($r->baseComisionable),
            'monto_comision' => $this->monto($r->montoComision),
            'neto_anfitrion' => $this->monto($r->netoAnfitrion),
            'porcentaje_aplicado' => $r->porcentajeAplicado,
            'regla_comision_id' => $r->reglaComisionId,
            'regla_cargo_servicio_id' => $r->reglaCargoServicioId,
        ];
    }
}
