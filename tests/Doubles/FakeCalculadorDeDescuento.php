<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Monetizacion\Domain\FinanciamientoDescuento;
use Src\Promociones\Application\Port\CalculadorDeDescuento;
use Src\Promociones\Application\Port\DescuentoAplicado;
use Src\Promociones\Application\Port\SolicitudDescuento;

final class FakeCalculadorDeDescuento implements CalculadorDeDescuento
{
    private ?DescuentoAplicado $descuento = null;

    public function descuentoAplicable(SolicitudDescuento $solicitud): ?DescuentoAplicado
    {
        return $this->descuento;
    }

    public function devolviendo(
        int $centavos,
        string $promocionId = 'promo-1',
        FinanciamientoDescuento $financiamiento = FinanciamientoDescuento::ANFITRION,
        string $moneda = 'BOB',
    ): self {
        $this->descuento = new DescuentoAplicado($promocionId, $centavos, $moneda, $financiamiento);

        return $this;
    }
}
