<?php

declare(strict_types=1);

namespace Src\Relaciones\Domain\Entity\AcuerdoComercial;

use DateTimeImmutable;
use Src\Relaciones\Domain\Enum\EstadoAcuerdo;

/**
 * Lectura de los datos del acuerdo.
 *
 * Parte de `AcuerdoComercial`: usa sus campos privados.
 */
trait Lectura
{
    public function acuerdoId(): string
    {
        return $this->acuerdoId;
    }

    public function anfitrionId(): string
    {
        return $this->anfitrionId;
    }

    public function reglaComisionId(): ?string
    {
        return $this->reglaComisionId;
    }

    public function estado(): EstadoAcuerdo
    {
        return $this->estado;
    }

    public function fechaInicio(): DateTimeImmutable
    {
        return $this->fechaInicio;
    }

    public function fechaFin(): ?DateTimeImmutable
    {
        return $this->fechaFin;
    }

    /** @return array<string, mixed> */
    public function condiciones(): array
    {
        return $this->condiciones;
    }

    public function diasLiquidacion(): int
    {
        return $this->diasLiquidacion;
    }

    public function aprobadoPor(): ?string
    {
        return $this->aprobadoPor;
    }

    public function aprobadoEn(): ?DateTimeImmutable
    {
        return $this->aprobadoEn;
    }

    public function motivoEstado(): ?string
    {
        return $this->motivoEstado;
    }
}
