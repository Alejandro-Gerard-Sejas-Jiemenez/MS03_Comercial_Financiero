<?php

declare(strict_types=1);

namespace Src\Comercial\Domain\Policy;

use DateTimeImmutable;
use Src\Comercial\Domain\Entity\Promocion;
use Src\Shared\Domain\Money;

/**
 * Entre las promociones automaticas que aplican a una reserva (temporada, ultimo
 * minuto, estadia prolongada) gana la de mayor descuento para el cliente.
 */
final class SelectorDePromocion
{
    /**
     * @param  list<Promocion>  $candidatas
     * @return Promocion|null null si ninguna aplica o si el mejor descuento es cero
     */
    public function mejorEntre(
        array $candidatas,
        int $noches,
        DateTimeImmutable $fecha,
        ?string $alojamientoId,
        ?string $anfitrionId,
        Money $subtotal,
    ): ?Promocion {
        $mejor = null;
        $mejorMonto = Money::cero($subtotal->moneda());

        foreach ($candidatas as $promocion) {
            if (! $promocion->aplicaA($noches, $fecha, $alojamientoId, $anfitrionId)) {
                continue;
            }

            $monto = $promocion->calcularDescuento($subtotal);

            if ($monto->esMayorQue($mejorMonto)) {
                $mejor = $promocion;
                $mejorMonto = $monto;
            }
        }

        return $mejor === null || $mejorMonto->esCero() ? null : $mejor;
    }
}
