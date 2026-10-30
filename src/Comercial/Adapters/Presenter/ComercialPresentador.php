<?php

declare(strict_types=1);

namespace Src\Comercial\Adapters\Presenter;

use Src\Comercial\Application\Port\DescuentoAplicado;
use Src\Comercial\Application\Response\PromocionResponse;
use Src\Shared\Adapters\Presenter\PresentadorBase;

/** Presentador de Comercial: convierte los Response del caso de uso al arreglo que sale por HTTP. */
final class ComercialPresentador extends PresentadorBase
{
    protected function objeto(object $r): mixed
    {
        return match (true) {
            $r instanceof PromocionResponse => $this->promocion($r),
            $r instanceof DescuentoAplicado => $this->descuentoAplicado($r),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function promocion(PromocionResponse $r): array
    {
        return [
            'promocion_id' => $r->promocionId,
            'codigo' => $r->codigo,
            'nombre' => $r->nombre,
            'tipo' => $r->tipo,
            'tipo_descuento' => $r->tipoDescuento,
            'valor' => $r->valor,
            'moneda' => $r->moneda,
            'creada_por_rol' => $r->creadaPorRol,
            'creada_por' => $r->creadaPor,
            'anfitrion_id' => $r->anfitrionId,
            'financiada_por' => $r->financiadaPor,
            'min_noches' => $r->minNoches,
            'vigente_desde' => $this->fecha($r->vigenteDesde),
            'vigente_hasta' => $this->fecha($r->vigenteHasta),
            'max_usos' => $r->maxUsos,
            'usos_actuales' => $r->usosActuales,
            'estado' => $r->estado,
            'alojamientos' => $r->alojamientos,
        ];
    }

    /** @return array<string, mixed> */
    private function descuentoAplicado(DescuentoAplicado $r): array
    {
        return [
            'promocion_id' => $r->promocionId,
            'descuento_centavos' => $r->descuentoCentavos,
            'moneda' => $r->moneda,
            'financiamiento' => $r->financiamiento->value,
        ];
    }
}
