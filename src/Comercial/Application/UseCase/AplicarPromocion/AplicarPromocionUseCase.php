<?php

declare(strict_types=1);

namespace Src\Comercial\Application\UseCase\AplicarPromocion;

use Src\Comercial\Application\Port\DescuentoAplicado;
use Src\Comercial\Application\Port\SolicitudDescuento;
use Src\Comercial\Application\Response\PromocionResponse;

/**
 * Entrada del caso de uso AplicarPromocion (Clean Architecture: Input Boundary).
 * Los adaptadores (controladores, consolas, suscriptores) dependen de esta interfaz, no del servicio.
 */
interface AplicarPromocionUseCase
{
    public function calcularDescuento(SolicitudDescuento $solicitud): ?DescuentoAplicado;

    public function descuentoAplicable(SolicitudDescuento $solicitud): ?DescuentoAplicado;

    public function registrarUso(RegistrarUsoDePromocionRequest $comando): PromocionResponse;
}
