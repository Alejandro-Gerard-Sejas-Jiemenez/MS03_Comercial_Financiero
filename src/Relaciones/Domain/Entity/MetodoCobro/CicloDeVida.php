<?php

declare(strict_types=1);

namespace Src\Relaciones\Domain\Entity\MetodoCobro;

use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/**
 * Estado del metodo de cobro: principal, activo y verificado.
 *
 * Parte de `MetodoCobro`: usa sus campos privados.
 */
trait CicloDeVida
{
    public function marcarPrincipal(): void
    {
        if (! $this->activo) {
            throw new ReglaDeNegocioViolada('Un metodo de cobro dado de baja no puede ser el principal');
        }

        $this->esPrincipal = true;
    }

    public function dejarDeSerPrincipal(): void
    {
        $this->esPrincipal = false;
    }

    /** idempotente, verificar dos veces no cambia nada. */
    /** Baja logica: deja de usarse y deja de ser el principal. */
    public function desactivar(): void
    {
        $this->activo = false;
        $this->esPrincipal = false;
    }

    public function reactivar(): void
    {
        $this->activo = true;
    }

    public function estaActivo(): bool
    {
        return $this->activo;
    }

    public function verificar(): void
    {
        $this->verificado = true;
    }

    /**
     * Solo el metodo principal y verificado sirve para pagarle al anfitrion.
     * Es la condicion que consulta Financiero por `MetodoCobroQuery`.
     */
    public function sirveParaLiquidar(): bool
    {
        return $this->activo && $this->esPrincipal && $this->verificado;
    }
}
