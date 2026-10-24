<?php

declare(strict_types=1);

namespace Src\Monetizacion\Domain;

use DateTimeImmutable;
use DomainException;
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
    ) {
    }

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
            throw new DomainException('El monto fijo no puede ser negativo');
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

    /**
     * Aplica la regla sobre una base. Es la formula del documento:
     * comision = base_comisionable x porcentaje.
     */
    public function calcular(Money $base): Money
    {
        if ($base->esNegativo()) {
            throw new DomainException('La base de calculo no puede ser negativa');
        }

        if ($this->tipoCalculo === TipoCalculo::PORCENTAJE) {
            return $base->porPorcentaje((string) $this->porcentaje);
        }

        $monto = $this->montoFijo;

        if ($monto === null) {
            throw new DomainException("La regla {$this->reglaId} es MONTO_FIJO y no tiene monto");
        }

        if ($monto->moneda() !== $base->moneda()) {
            throw new DomainException(
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

    public function renombrar(string $nombre): void
    {
        $this->nombre = self::validarNombre($nombre);
    }

    public function cambiarPorcentaje(string $porcentaje): void
    {
        if ($this->tipoCalculo !== TipoCalculo::PORCENTAJE) {
            throw new DomainException("La regla {$this->reglaId} no se calcula por porcentaje");
        }

        self::validarPorcentaje($porcentaje);
        $this->porcentaje = $porcentaje;
    }

    public function cambiarMontoFijo(Money $monto): void
    {
        if ($this->tipoCalculo !== TipoCalculo::MONTO_FIJO) {
            throw new DomainException("La regla {$this->reglaId} no se calcula por monto fijo");
        }

        if ($monto->esNegativo()) {
            throw new DomainException('El monto fijo no puede ser negativo');
        }

        $this->montoFijo = $monto;
    }

    public function cerrarVigencia(DateTimeImmutable $vigenteHasta): void
    {
        self::validarVigencia($this->vigenteDesde, $vigenteHasta);
        $this->vigenteHasta = self::soloFecha($vigenteHasta);
    }

    public function desactivar(): void
    {
        $this->estado = EstadoRegla::INACTIVA;
    }

    public function estaActiva(): bool
    {
        return $this->estado === EstadoRegla::ACTIVA;
    }

    public function reglaId(): string
    {
        return $this->reglaId;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function concepto(): Concepto
    {
        return $this->concepto;
    }

    public function tipoCalculo(): TipoCalculo
    {
        return $this->tipoCalculo;
    }

    public function porcentaje(): ?string
    {
        return $this->porcentaje;
    }

    public function montoFijo(): ?Money
    {
        return $this->montoFijo;
    }

    public function moneda(): string
    {
        return $this->moneda;
    }

    public function categoriaAlojamiento(): ?string
    {
        return $this->categoriaAlojamiento;
    }

    public function nivelAnfitrion(): ?string
    {
        return $this->nivelAnfitrion;
    }

    public function vigenteDesde(): DateTimeImmutable
    {
        return $this->vigenteDesde;
    }

    public function vigenteHasta(): ?DateTimeImmutable
    {
        return $this->vigenteHasta;
    }

    public function estado(): EstadoRegla
    {
        return $this->estado;
    }

    public function creadoPor(): string
    {
        return $this->creadoPor;
    }

    /** Una regla con alcance mas especifico gana sobre una general. */
    public function especificidad(): int
    {
        return ($this->categoriaAlojamiento === null ? 0 : 2) + ($this->nivelAnfitrion === null ? 0 : 1);
    }

    private static function validarNombre(string $nombre): string
    {
        $limpio = trim($nombre);

        if ($limpio === '') {
            throw new DomainException('La regla necesita un nombre');
        }

        return $limpio;
    }

    private static function validarPorcentaje(string $porcentaje): void
    {
        if (! is_numeric($porcentaje)) {
            throw new DomainException("Porcentaje invalido: {$porcentaje}");
        }

        $valor = (float) $porcentaje;

        if ($valor < 0 || $valor > 100) {
            throw new DomainException("El porcentaje debe estar entre 0 y 100, recibido {$porcentaje}");
        }
    }

    private static function validarVigencia(DateTimeImmutable $desde, ?DateTimeImmutable $hasta): void
    {
        if ($hasta !== null && self::soloFecha($hasta) < self::soloFecha($desde)) {
            throw new DomainException('La vigencia no puede terminar antes de empezar');
        }
    }

    private static function soloFecha(DateTimeImmutable $fecha): DateTimeImmutable
    {
        return new DateTimeImmutable($fecha->format('Y-m-d') . ' 00:00:00');
    }
}
