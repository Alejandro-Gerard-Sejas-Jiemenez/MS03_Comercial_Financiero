<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use DomainException;

/**
 * Monto de dinero. Objeto de valor inmutable.
 *
 * Se guarda en centavos (entero) porque las reglas de dinero de MS-03
 * -- total = subtotal - descuento + cargo_servicio, comision = base x %,
 * neto = bruto - comisiones - reembolsos -- no toleran el error de redondeo
 * del punto flotante. En PostgreSQL las columnas son NUMERIC(12,2): la
 * conversion vive en los repositorios (`deDecimal()` / `aDecimal()`).
 */
final class Money
{
    /** Las monedas del CHECK de la base de datos. */
    private const MONEDAS = ['BOB', 'USD'];

    private function __construct(
        private readonly int $centavos,
        private readonly string $moneda,
    ) {
    }

    public static function deCentavos(int $centavos, string $moneda = 'BOB'): self
    {
        return new self($centavos, self::validarMoneda($moneda));
    }

    public static function cero(string $moneda = 'BOB'): self
    {
        return new self(0, self::validarMoneda($moneda));
    }

    /**
     * Lee la representacion decimal de NUMERIC(12,2): "1234.50", "1234.5", "1234", "-25.50".
     */
    public static function deDecimal(string $monto, string $moneda = 'BOB'): self
    {
        $limpio = trim($monto);

        if (preg_match('/^-?\d+(\.\d{1,2})?$/', $limpio) !== 1) {
            throw new DomainException("Monto decimal invalido: {$monto}");
        }

        $negativo = str_starts_with($limpio, '-');
        [$enteros, $decimales] = array_pad(explode('.', ltrim($limpio, '-'), 2), 2, '0');
        $centavos = (int) $enteros * 100 + (int) str_pad($decimales, 2, '0');

        return new self($negativo ? -$centavos : $centavos, self::validarMoneda($moneda));
    }

    /**
     * @param iterable<self> $montos
     */
    public static function sumaDe(iterable $montos, string $moneda = 'BOB'): self
    {
        $total = self::cero($moneda);

        foreach ($montos as $monto) {
            $total = $total->mas($monto);
        }

        return $total;
    }

    public function centavos(): int
    {
        return $this->centavos;
    }

    public function moneda(): string
    {
        return $this->moneda;
    }

    public function mas(self $otro): self
    {
        $this->asegurarMismaMoneda($otro);

        return new self($this->centavos + $otro->centavos, $this->moneda);
    }

    public function menos(self $otro): self
    {
        $this->asegurarMismaMoneda($otro);

        return new self($this->centavos - $otro->centavos, $this->moneda);
    }

    public function multiplicadoPor(int $factor): self
    {
        return new self($this->centavos * $factor, $this->moneda);
    }

    /**
     * Aplica un porcentaje con dos decimales, como la columna NUMERIC(5,2).
     * Redondea el medio centavo hacia arriba (half-up) alejandose del cero.
     */
    public function porPorcentaje(string|int|float $porcentaje): self
    {
        $centesimas = self::porcentajeEnCentesimas($porcentaje);

        return new self(self::dividirRedondeando($this->centavos * $centesimas, 10_000), $this->moneda);
    }

    public function negado(): self
    {
        return new self(-$this->centavos, $this->moneda);
    }

    public function valorAbsoluto(): self
    {
        return new self(abs($this->centavos), $this->moneda);
    }

    public function esNegativo(): bool
    {
        return $this->centavos < 0;
    }

    public function esCero(): bool
    {
        return $this->centavos === 0;
    }

    public function esPositivo(): bool
    {
        return $this->centavos > 0;
    }

    public function esMayorQue(self $otro): bool
    {
        $this->asegurarMismaMoneda($otro);

        return $this->centavos > $otro->centavos;
    }

    public function esMenorQue(self $otro): bool
    {
        $this->asegurarMismaMoneda($otro);

        return $this->centavos < $otro->centavos;
    }

    public function esMenorOIgualQue(self $otro): bool
    {
        return ! $this->esMayorQue($otro);
    }

    public function igualA(self $otro): bool
    {
        return $this->centavos === $otro->centavos && $this->moneda === $otro->moneda;
    }

    /** Devuelve el monto como lo espera NUMERIC(12,2): "1234.50". */
    public function aDecimal(): string
    {
        $absoluto = abs($this->centavos);

        return sprintf(
            '%s%d.%02d',
            $this->centavos < 0 ? '-' : '',
            intdiv($absoluto, 100),
            $absoluto % 100,
        );
    }

    public function __toString(): string
    {
        return $this->aDecimal() . ' ' . $this->moneda;
    }

    private function asegurarMismaMoneda(self $otro): void
    {
        if ($this->moneda !== $otro->moneda) {
            throw new DomainException(
                "No se pueden operar montos en {$this->moneda} y {$otro->moneda}",
            );
        }
    }

    private static function validarMoneda(string $moneda): string
    {
        $normalizada = strtoupper(trim($moneda));

        if (! in_array($normalizada, self::MONEDAS, true)) {
            throw new DomainException("Moneda no soportada: {$moneda}");
        }

        return $normalizada;
    }

    /** 15.50 % -> 1550 centesimas de porcentaje. */
    private static function porcentajeEnCentesimas(string|int|float $porcentaje): int
    {
        if (! is_numeric($porcentaje)) {
            throw new DomainException("Porcentaje invalido: {$porcentaje}");
        }

        $valor = (float) $porcentaje;

        if ($valor < 0 || $valor > 100) {
            throw new DomainException("El porcentaje debe estar entre 0 y 100, recibido {$porcentaje}");
        }

        return (int) round($valor * 100);
    }

    /** Division entera con redondeo half-up alejandose del cero. */
    private static function dividirRedondeando(int $numerador, int $divisor): int
    {
        $signo = $numerador < 0 ? -1 : 1;

        return $signo * intdiv(2 * abs($numerador) + $divisor, 2 * $divisor);
    }
}
