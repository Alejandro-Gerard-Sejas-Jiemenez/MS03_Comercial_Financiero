<?php

declare(strict_types=1);

namespace Src\Comercial\Application\Port;

/**
 * Puerto que consume Financiero para no depender de las clases internas de
 * Comercial. Lo implementa `AplicarPromocionService`.
 */
interface CalculadorDeDescuento
{
    /**
     * Descuento aplicable a la solicitud, o null si no hay promocion aplicable.
     * Lanza DomainException si el cupon existe pero no es valido (vencido,
     * pausado, sin usos disponibles o por debajo de las noches minimas).
     */
    public function descuentoAplicable(SolicitudDescuento $solicitud): ?DescuentoAplicado;
}
