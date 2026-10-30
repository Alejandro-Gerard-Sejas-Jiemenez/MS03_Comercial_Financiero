<?php

declare(strict_types=1);

namespace Src\Monetizacion\Domain\Entity\ReglaMonetizacion;

use DateTimeImmutable;
use Src\Monetizacion\Domain\Enum\EstadoRegla;
use Src\Monetizacion\Domain\Enum\TipoCalculo;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;

/**
 * Calculo de la regla y respuesta a "¿aplica a esta categoria y nivel?".
 *
 * Parte de `ReglaMonetizacion`: usa sus campos privados.
 */
trait Aplicabilidad
{
    /**
     * Aplica la regla sobre una base. Es la formula del documento:
     * comision = base_comisionable x porcentaje.
     */
    public function calcular(Money $base): Money
    {
        if ($base->esNegativo()) {
            throw new ReglaDeNegocioViolada('La base de calculo no puede ser negativa');
        }

        if ($this->tipoCalculo === TipoCalculo::PORCENTAJE) {
            return $base->porPorcentaje((string) $this->porcentaje);
        }

        $monto = $this->montoFijo;

        if ($monto === null) {
            throw new ReglaDeNegocioViolada("La regla {$this->reglaId} es MONTO_FIJO y no tiene monto");
        }

        if ($monto->moneda() !== $base->moneda()) {
            throw new ReglaDeNegocioViolada(
                "La regla {$this->reglaId} esta en {$monto->moneda()} y la base en {$base->moneda()}",
            );
        }

        return $monto;
    }

    /** Vigente en la fecha dada y con alcance compatible (NULL en la regla = aplica a todos). */
    public function aplicaA(
        DateTimeImmutable $fecha,
        ?string $categoriaAlojamiento = null,
        ?string $nivelAnfitrion = null,
    ): bool {
        if ($this->estado !== EstadoRegla::ACTIVA) {
            return false;
        }

        $dia = self::soloFecha($fecha);

        if ($dia < $this->vigenteDesde) {
            return false;
        }

        if ($this->vigenteHasta !== null && $dia > $this->vigenteHasta) {
            return false;
        }

        if ($this->categoriaAlojamiento !== null && $this->categoriaAlojamiento !== $categoriaAlojamiento) {
            return false;
        }

        return $this->nivelAnfitrion === null || $this->nivelAnfitrion === $nivelAnfitrion;
    }

    /** Una regla con alcance mas especifico gana sobre una general. */
    public function especificidad(): int
    {
        return ($this->categoriaAlojamiento === null ? 0 : 2) + ($this->nivelAnfitrion === null ? 0 : 1);
    }
}
