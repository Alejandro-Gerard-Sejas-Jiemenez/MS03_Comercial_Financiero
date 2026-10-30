<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Entity\Liquidacion;

use DateTimeImmutable;
use Src\Financiero\Domain\Enum\EstadoLiquidacion;
use Src\Shared\Domain\Money;

/**
 * Lectura de los datos y totales de la liquidacion.
 *
 * Parte de `Liquidacion`: usa sus campos privados.
 */
trait Lectura
{
    public function totalNeto(): Money
    {
        return $this->totalBruto->menos($this->totalComisiones)->menos($this->totalReembolsos);
    }

    public function liquidacionId(): string
    {
        return $this->liquidacionId;
    }

    public function anfitrionId(): string
    {
        return $this->anfitrionId;
    }

    public function metodoId(): ?string
    {
        return $this->metodoId;
    }

    public function periodoDesde(): DateTimeImmutable
    {
        return $this->periodoDesde;
    }

    public function periodoHasta(): DateTimeImmutable
    {
        return $this->periodoHasta;
    }

    public function totalBruto(): Money
    {
        return $this->totalBruto;
    }

    public function totalComisiones(): Money
    {
        return $this->totalComisiones;
    }

    public function totalReembolsos(): Money
    {
        return $this->totalReembolsos;
    }

    public function moneda(): string
    {
        return $this->totalBruto->moneda();
    }

    public function estado(): EstadoLiquidacion
    {
        return $this->estado;
    }

    public function aprobadoPor(): ?string
    {
        return $this->aprobadoPor;
    }

    public function referenciaTransferencia(): ?string
    {
        return $this->referenciaTransferencia;
    }
}
