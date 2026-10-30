<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Entity\Comision;

use DateTimeImmutable;
use Src\Financiero\Domain\Enum\EstadoComision;
use Src\Shared\Domain\Money;

/**
 * Lectura de los datos de la comision.
 *
 * Parte de `Comision`: usa sus campos privados.
 */
trait Lectura
{
    public function comisionId(): string
    {
        return $this->comisionId;
    }

    public function pagoId(): string
    {
        return $this->pagoId;
    }

    public function reservaId(): string
    {
        return $this->reservaId;
    }

    public function anfitrionId(): string
    {
        return $this->anfitrionId;
    }

    public function reglaId(): ?string
    {
        return $this->reglaId;
    }

    public function acuerdoId(): ?string
    {
        return $this->acuerdoId;
    }

    public function liquidacionId(): ?string
    {
        return $this->liquidacionId;
    }

    public function baseComisionable(): Money
    {
        return $this->baseComisionable;
    }

    public function porcentajeAplicado(): ?string
    {
        return $this->porcentajeAplicado;
    }

    public function montoComision(): Money
    {
        return $this->montoComision;
    }

    public function montoNetoAnfitrion(): Money
    {
        return $this->montoNetoAnfitrion;
    }

    public function moneda(): string
    {
        return $this->montoComision->moneda();
    }

    public function estado(): EstadoComision
    {
        return $this->estado;
    }

    public function liquidableDesde(): ?DateTimeImmutable
    {
        return $this->liquidableDesde;
    }
}
