<?php

declare(strict_types=1);

namespace Src\Monetizacion\Domain\Entity;

use DateTimeImmutable;
use Src\Monetizacion\Domain\Entity\ReglaMonetizacion\Aplicabilidad;
use Src\Monetizacion\Domain\Entity\ReglaMonetizacion\CicloDeVida;
use Src\Monetizacion\Domain\Entity\ReglaMonetizacion\Edicion;
use Src\Monetizacion\Domain\Entity\ReglaMonetizacion\Lectura;
use Src\Monetizacion\Domain\Enum\Concepto;
use Src\Monetizacion\Domain\Enum\EstadoRegla;
use Src\Monetizacion\Domain\Enum\TipoCalculo;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;

/**
 * Regla de monetizacion vigente (F16): un porcentaje o un monto fijo, con
 * alcance opcional por categoria de alojamiento y nivel de anfitrion.
 *
 * Entidad pura: no conoce Eloquent ni Laravel. La fila de la base de datos se
 * traduce con `reconstituir()`.
 */
final class ReglaMonetizacion
{
    use Aplicabilidad;
    use CicloDeVida;
    use Edicion;
    use Lectura;

    private function __construct(
        private readonly string $reglaId,
        private string $nombre,
        private readonly Concepto $concepto,
        private readonly TipoCalculo $tipoCalculo,
        private ?string $porcentaje,
        private ?Money $montoFijo,
        private readonly string $moneda,
        private readonly ?string $categoriaAlojamiento,
        private readonly ?string $nivelAnfitrion,
        private readonly DateTimeImmutable $vigenteDesde,
        private ?DateTimeImmutable $vigenteHasta,
        private EstadoRegla $estado,
        private readonly string $creadoPor,
    ) {}

    public static function porPorcentaje(
        string $reglaId,
        string $nombre,
        Concepto $concepto,
        string $porcentaje,
        DateTimeImmutable $vigenteDesde,
        string $creadoPor,
        ?DateTimeImmutable $vigenteHasta = null,
        ?string $categoriaAlojamiento = null,
        ?string $nivelAnfitrion = null,
        string $moneda = 'BOB',
    ): self {
        self::validarPorcentaje($porcentaje);
        self::validarVigencia($vigenteDesde, $vigenteHasta);

        return new self(
            $reglaId,
            self::validarNombre($nombre),
            $concepto,
            TipoCalculo::PORCENTAJE,
            $porcentaje,
            null,
            Money::cero($moneda)->moneda(),
            $categoriaAlojamiento,
            $nivelAnfitrion,
            self::soloFecha($vigenteDesde),
            $vigenteHasta === null ? null : self::soloFecha($vigenteHasta),
            EstadoRegla::ACTIVA,
            $creadoPor,
        );
    }

    public static function porMontoFijo(
        string $reglaId,
        string $nombre,
        Concepto $concepto,
        Money $montoFijo,
        DateTimeImmutable $vigenteDesde,
        string $creadoPor,
        ?DateTimeImmutable $vigenteHasta = null,
        ?string $categoriaAlojamiento = null,
        ?string $nivelAnfitrion = null,
    ): self {
        if ($montoFijo->esNegativo()) {
            throw new ReglaDeNegocioViolada('El monto fijo no puede ser negativo');
        }

        self::validarVigencia($vigenteDesde, $vigenteHasta);

        return new self(
            $reglaId,
            self::validarNombre($nombre),
            $concepto,
            TipoCalculo::MONTO_FIJO,
            null,
            $montoFijo,
            $montoFijo->moneda(),
            $categoriaAlojamiento,
            $nivelAnfitrion,
            self::soloFecha($vigenteDesde),
            $vigenteHasta === null ? null : self::soloFecha($vigenteHasta),
            EstadoRegla::ACTIVA,
            $creadoPor,
        );
    }

    /** Reconstruye la entidad desde la base de datos, sin revalidar reglas de creacion. */
    public static function reconstituir(
        string $reglaId,
        string $nombre,
        Concepto $concepto,
        TipoCalculo $tipoCalculo,
        ?string $porcentaje,
        ?Money $montoFijo,
        string $moneda,
        ?string $categoriaAlojamiento,
        ?string $nivelAnfitrion,
        DateTimeImmutable $vigenteDesde,
        ?DateTimeImmutable $vigenteHasta,
        EstadoRegla $estado,
        string $creadoPor,
    ): self {
        return new self(
            $reglaId,
            $nombre,
            $concepto,
            $tipoCalculo,
            $porcentaje,
            $montoFijo,
            $moneda,
            $categoriaAlojamiento,
            $nivelAnfitrion,
            $vigenteDesde,
            $vigenteHasta,
            $estado,
            $creadoPor,
        );
    }
}
