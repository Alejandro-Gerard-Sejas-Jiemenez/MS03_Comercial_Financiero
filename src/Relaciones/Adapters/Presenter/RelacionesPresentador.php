<?php

declare(strict_types=1);

namespace Src\Relaciones\Adapters\Presenter;

use Src\Relaciones\Application\Response\AcuerdoComercialResponse;
use Src\Relaciones\Application\Response\MetodoCobroResponse;
use Src\Shared\Adapters\Presenter\PresentadorBase;

/** Presentador de Relaciones: convierte los Response del caso de uso al arreglo que sale por HTTP. */
final class RelacionesPresentador extends PresentadorBase
{
    protected function objeto(object $r): mixed
    {
        return match (true) {
            $r instanceof AcuerdoComercialResponse => $this->acuerdoComercial($r),
            $r instanceof MetodoCobroResponse => $this->metodoCobro($r),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function acuerdoComercial(AcuerdoComercialResponse $r): array
    {
        return [
            'acuerdo_id' => $r->acuerdoId,
            'anfitrion_id' => $r->anfitrionId,
            'regla_comision_id' => $r->reglaComisionId,
            'estado' => $r->estado,
            'fecha_inicio' => $this->fecha($r->fechaInicio),
            'fecha_fin' => $this->fecha($r->fechaFin),
            'condiciones' => $r->condiciones,
            'dias_liquidacion' => $r->diasLiquidacion,
            'aprobado_por' => $r->aprobadoPor,
            'aprobado_en' => $this->fecha($r->aprobadoEn),
            'motivo_estado' => $r->motivoEstado,
        ];
    }

    /** @return array<string, mixed> */
    private function metodoCobro(MetodoCobroResponse $r): array
    {
        return [
            'metodo_id' => $r->metodoId,
            'anfitrion_id' => $r->anfitrionId,
            'tipo' => $r->tipo,
            'banco' => $r->banco,
            'titular' => $r->titular,
            'ultimos_digitos' => $r->ultimosDigitos,
            'es_principal' => $r->esPrincipal,
            'verificado' => $r->verificado,
            'activo' => $r->activo,
        ];
    }
}
