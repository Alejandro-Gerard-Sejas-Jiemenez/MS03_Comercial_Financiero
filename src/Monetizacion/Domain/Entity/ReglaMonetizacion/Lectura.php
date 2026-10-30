<?php

declare(strict_types=1);

namespace Src\Monetizacion\Domain\Entity\ReglaMonetizacion;

use DateTimeImmutable;
use Src\Monetizacion\Domain\Enum\Concepto;
use Src\Monetizacion\Domain\Enum\EstadoRegla;
use Src\Monetizacion\Domain\Enum\TipoCalculo;
use Src\Shared\Domain\Money;

/**
 * Lectura de los datos de la regla.
 *
 * Parte de `ReglaMonetizacion`: usa sus campos privados.
 */
trait Lectura
{
    public function reglaId(): string
    {
        return $this->reglaId;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function concepto(): Concepto
    {
        return $this->concepto;
    }

    public function tipoCalculo(): TipoCalculo
    {
        return $this->tipoCalculo;
    }

    public function porcentaje(): ?string
    {
        return $this->porcentaje;
    }

    public function montoFijo(): ?Money
    {
        return $this->montoFijo;
    }

    public function moneda(): string
    {
        return $this->moneda;
    }

    public function categoriaAlojamiento(): ?string
    {
        return $this->categoriaAlojamiento;
    }

    public function nivelAnfitrion(): ?string
    {
        return $this->nivelAnfitrion;
    }

    public function vigenteDesde(): DateTimeImmutable
    {
        return $this->vigenteDesde;
    }

    public function vigenteHasta(): ?DateTimeImmutable
    {
        return $this->vigenteHasta;
    }

    public function estado(): EstadoRegla
    {
        return $this->estado;
    }

    public function creadoPor(): string
    {
        return $this->creadoPor;
    }
}
