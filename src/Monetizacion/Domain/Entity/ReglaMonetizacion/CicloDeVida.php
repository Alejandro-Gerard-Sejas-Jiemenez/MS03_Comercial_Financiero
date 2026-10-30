<?php

declare(strict_types=1);

namespace Src\Monetizacion\Domain\Entity\ReglaMonetizacion;

use Src\Monetizacion\Domain\Enum\EstadoRegla;

/**
 * Estado de la regla: activa o inactiva.
 *
 * Parte de `ReglaMonetizacion`: usa sus campos privados.
 */
trait CicloDeVida
{
    public function desactivar(): void
    {
        $this->estado = EstadoRegla::INACTIVA;
    }

    public function estaActiva(): bool
    {
        return $this->estado === EstadoRegla::ACTIVA;
    }
}
