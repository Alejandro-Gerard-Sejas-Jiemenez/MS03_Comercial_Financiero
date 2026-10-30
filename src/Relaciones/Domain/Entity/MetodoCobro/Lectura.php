<?php

declare(strict_types=1);

namespace Src\Relaciones\Domain\Entity\MetodoCobro;

use Src\Relaciones\Domain\Enum\TipoMetodoCobro;

/**
 * Lectura de los datos del metodo de cobro.
 *
 * Parte de `MetodoCobro`: usa sus campos privados.
 */
trait Lectura
{
    public function metodoId(): string
    {
        return $this->metodoId;
    }

    public function anfitrionId(): string
    {
        return $this->anfitrionId;
    }

    public function tipo(): TipoMetodoCobro
    {
        return $this->tipo;
    }

    public function banco(): ?string
    {
        return $this->banco;
    }

    public function titular(): string
    {
        return $this->titular;
    }

    public function numeroCifrado(): string
    {
        return $this->numeroCifrado;
    }

    public function ultimosDigitos(): ?string
    {
        return $this->ultimosDigitos;
    }

    public function esPrincipal(): bool
    {
        return $this->esPrincipal;
    }

    public function estaVerificado(): bool
    {
        return $this->verificado;
    }

    public function esDe(string $anfitrionId): bool
    {
        return $this->anfitrionId === $anfitrionId;
    }
}
