<?php

declare(strict_types=1);

namespace Src\Monetizacion\Domain;

use DomainException;
use Src\Shared\Domain\Money;

/**
 * Servicio de dominio con las formulas de dinero de MS-03 (F16).
 *
 * Son las reglas que el documento de arquitectura manda tener en PHP puro, sin
 * base de datos ni Laravel:
 *
 *   total           = subtotal - descuento + cargo_servicio
 *   base_comisionable = subtotal - descuento financiado por el anfitrion
 *   monto_comision  = base_comisionable x porcentaje
 *   neto_anfitrion  = base_comisionable - monto_comision
 *
 * Supuesto: el cargo de servicio al cliente se calcula sobre lo que realmente
 * paga por el alojamiento (subtotal - descuento), no sobre el subtotal bruto.
 */
final class CalculadoraComision
{
    public function calcular(
        Money $subtotal,
        Money $descuento,
        FinanciamientoDescuento $financiamiento = FinanciamientoDescuento::ANFITRION,
        ?ReglaMonetizacion $reglaComision = null,
        ?ReglaMonetizacion $reglaCargoServicio = null,
    ): DesgloseComercial {
        $this->validarMontos($subtotal, $descuento);
        $this->validarConceptos($reglaComision, $reglaCargoServicio);

        $moneda = $subtotal->moneda();
        $pagaPorAlojamiento = $subtotal->menos($descuento);

        $cargoServicio = $reglaCargoServicio?->calcular($pagaPorAlojamiento) ?? Money::cero($moneda);
        $total = $pagaPorAlojamiento->mas($cargoServicio);

        // Si el descuento lo financia la plataforma, el anfitrion no lo absorbe:
        // la comision se calcula sobre el subtotal completo.
        $baseComisionable = $financiamiento === FinanciamientoDescuento::ANFITRION
            ? $pagaPorAlojamiento
            : $subtotal;

        $montoComision = $reglaComision?->calcular($baseComisionable) ?? Money::cero($moneda);

        if ($montoComision->esMayorQue($baseComisionable)) {
            throw new DomainException('La comision no puede superar la base comisionable');
        }

        return new DesgloseComercial(
            subtotal: $subtotal,
            descuento: $descuento,
            cargoServicio: $cargoServicio,
            total: $total,
            baseComisionable: $baseComisionable,
            montoComision: $montoComision,
            netoAnfitrion: $baseComisionable->menos($montoComision),
            porcentajeAplicado: $reglaComision?->tipoCalculo() === TipoCalculo::PORCENTAJE
                ? $reglaComision->porcentaje()
                : null,
            reglaComisionId: $reglaComision?->reglaId(),
            reglaCargoServicioId: $reglaCargoServicio?->reglaId(),
        );
    }

    private function validarMontos(Money $subtotal, Money $descuento): void
    {
        if ($subtotal->esNegativo()) {
            throw new DomainException('El subtotal no puede ser negativo');
        }

        if ($descuento->esNegativo()) {
            throw new DomainException('El descuento no puede ser negativo');
        }

        if ($descuento->esMayorQue($subtotal)) {
            throw new DomainException('El descuento no puede superar el subtotal');
        }
    }

    /**
     * Una regla de cargo al cliente no puede usarse como comision al anfitrion,
     * ni al reves: son conceptos distintos y cobran a personas distintas.
     */
    private function validarConceptos(
        ?ReglaMonetizacion $reglaComision,
        ?ReglaMonetizacion $reglaCargoServicio,
    ): void {
        if ($reglaComision !== null && $reglaComision->concepto() !== Concepto::COMISION_ANFITRION) {
            throw new DomainException(
                "La regla {$reglaComision->reglaId()} no es de concepto COMISION_ANFITRION",
            );
        }

        if (
            $reglaCargoServicio !== null
            && $reglaCargoServicio->concepto() !== Concepto::CARGO_SERVICIO_CLIENTE
        ) {
            throw new DomainException(
                "La regla {$reglaCargoServicio->reglaId()} no es de concepto CARGO_SERVICIO_CLIENTE",
            );
        }
    }
}
