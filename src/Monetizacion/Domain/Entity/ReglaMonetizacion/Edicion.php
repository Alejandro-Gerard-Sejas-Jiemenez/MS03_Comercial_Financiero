<?php

declare(strict_types=1);

namespace Src\Monetizacion\Domain\Entity\ReglaMonetizacion;

use DateTimeImmutable;
use Src\Monetizacion\Domain\Enum\TipoCalculo;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;

/**
 * Edicion de la regla y sus validaciones.
 *
 * Parte de `ReglaMonetizacion`: usa sus campos privados.
 */
trait Edicion
{
    public function renombrar(string $nombre): void
    {
        $this->nombre = self::validarNombre($nombre);
    }

    public function cambiarPorcentaje(string $porcentaje): void
    {
        if ($this->tipoCalculo !== TipoCalculo::PORCENTAJE) {
            throw new ReglaDeNegocioViolada("La regla {$this->reglaId} no se calcula por porcentaje");
        }

        self::validarPorcentaje($porcentaje);
        $this->porcentaje = $porcentaje;
    }

    public function cambiarMontoFijo(Money $monto): void
    {
        if ($this->tipoCalculo !== TipoCalculo::MONTO_FIJO) {
            throw new ReglaDeNegocioViolada("La regla {$this->reglaId} no se calcula por monto fijo");
        }

        if ($monto->esNegativo()) {
            throw new ReglaDeNegocioViolada('El monto fijo no puede ser negativo');
        }

        $this->montoFijo = $monto;
    }

    public function cerrarVigencia(DateTimeImmutable $vigenteHasta): void
    {
        self::validarVigencia($this->vigenteDesde, $vigenteHasta);
        $this->vigenteHasta = self::soloFecha($vigenteHasta);
    }

    private static function validarNombre(string $nombre): string
    {
        $limpio = trim($nombre);

        if ($limpio === '') {
            throw new ReglaDeNegocioViolada('La regla necesita un nombre');
        }

        return $limpio;
    }

    private static function validarPorcentaje(string $porcentaje): void
    {
        if (! is_numeric($porcentaje)) {
            throw new ReglaDeNegocioViolada("Porcentaje invalido: {$porcentaje}");
        }

        $valor = (float) $porcentaje;

        if ($valor < 0 || $valor > 100) {
            throw new ReglaDeNegocioViolada("El porcentaje debe estar entre 0 y 100, recibido {$porcentaje}");
        }
    }

    private static function validarVigencia(DateTimeImmutable $desde, ?DateTimeImmutable $hasta): void
    {
        if ($hasta !== null && self::soloFecha($hasta) < self::soloFecha($desde)) {
            throw new ReglaDeNegocioViolada('La vigencia no puede terminar antes de empezar');
        }
    }

    private static function soloFecha(DateTimeImmutable $fecha): DateTimeImmutable
    {
        return new DateTimeImmutable($fecha->format('Y-m-d').' 00:00:00');
    }
}
