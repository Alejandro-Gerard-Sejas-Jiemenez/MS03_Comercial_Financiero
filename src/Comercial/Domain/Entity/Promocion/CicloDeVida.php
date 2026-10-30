<?php

declare(strict_types=1);

namespace Src\Comercial\Domain\Entity\Promocion;

use DateTimeImmutable;
use Src\Comercial\Domain\Enum\EstadoPromocion;
use Src\Shared\Domain\Exception\ConflictoDeEstado;

/**
 * Cambios de estado: activar, pausar, cancelar y vencer.
 *
 * Parte de `Promocion`: usa sus campos privados.
 */
trait CicloDeVida
{
    /** Solo vence la que ya paso su `vigente_hasta`. */
    public function vencer(DateTimeImmutable $fecha): void
    {
        if ($this->estado->esTerminal()) {
            throw new ConflictoDeEstado(
                "La promocion {$this->promocionId} ya esta {$this->estado->value}",
            );
        }

        if (! $this->vigencia->terminoAntesDe($fecha)) {
            throw new ConflictoDeEstado(
                "La promocion {$this->promocionId} sigue vigente hasta "
                .$this->vigencia->hasta()->format('Y-m-d H:i:s'),
            );
        }

        $this->estado = EstadoPromocion::VENCIDA;
    }

    public function activar(): void
    {
        if ($this->estado !== EstadoPromocion::BORRADOR && $this->estado !== EstadoPromocion::PAUSADA) {
            throw new ConflictoDeEstado(
                "La promocion {$this->promocionId} esta {$this->estado->value} y no se puede activar",
            );
        }

        $this->estado = EstadoPromocion::ACTIVA;
    }

    public function pausar(): void
    {
        if ($this->estado !== EstadoPromocion::ACTIVA) {
            throw new ConflictoDeEstado(
                "La promocion {$this->promocionId} esta {$this->estado->value} y no se puede pausar",
            );
        }

        $this->estado = EstadoPromocion::PAUSADA;
    }

    public function cancelar(): void
    {
        if ($this->estado->esTerminal()) {
            throw new ConflictoDeEstado(
                "La promocion {$this->promocionId} ya esta {$this->estado->value}",
            );
        }

        $this->estado = EstadoPromocion::CANCELADA;
    }
}
