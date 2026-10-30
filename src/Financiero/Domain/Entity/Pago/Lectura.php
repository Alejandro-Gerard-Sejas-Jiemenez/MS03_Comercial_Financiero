<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Entity\Pago;

use Src\Financiero\Domain\Enum\EstadoPago;
use Src\Financiero\Domain\Enum\MetodoPago;
use Src\Shared\Domain\Money;

/**
 * Lectura de los datos y totales del pago.
 *
 * Parte de `Pago`: usa sus campos privados.
 */
trait Lectura
{
    /** `monto_total = subtotal_alojamiento - descuento + cargo_servicio`. */
    public function total(): Money
    {
        return $this->subtotal->menos($this->descuento)->mas($this->cargoServicio);
    }

    /**
     * Lo que el anfitrion vendio: el alojamiento menos el descuento. El cargo de
     * servicio no entra, porque es ingreso de la plataforma.
     */
    public function montoDelAnfitrion(): Money
    {
        return $this->subtotal->menos($this->descuento);
    }

    public function pagoId(): string
    {
        return $this->pagoId;
    }

    public function reservaId(): string
    {
        return $this->reservaId;
    }

    public function clienteId(): string
    {
        return $this->clienteId;
    }

    public function anfitrionId(): string
    {
        return $this->anfitrionId;
    }

    public function promocionId(): ?string
    {
        return $this->promocionId;
    }

    public function subtotal(): Money
    {
        return $this->subtotal;
    }

    public function descuento(): Money
    {
        return $this->descuento;
    }

    public function cargoServicio(): Money
    {
        return $this->cargoServicio;
    }

    public function moneda(): string
    {
        return $this->subtotal->moneda();
    }

    public function metodo(): MetodoPago
    {
        return $this->metodo;
    }

    public function estado(): EstadoPago
    {
        return $this->estado;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function sagaId(): ?string
    {
        return $this->sagaId;
    }

    public function pasarela(): ?string
    {
        return $this->pasarela;
    }

    public function referenciaPasarela(): ?string
    {
        return $this->referenciaPasarela;
    }
}
