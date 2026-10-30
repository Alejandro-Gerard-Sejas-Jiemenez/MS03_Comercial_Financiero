<?php

declare(strict_types=1);

namespace Src\Shared\Application\Response;

use Src\Shared\Domain\Money;

/** Modelo de respuesta de un monto: centavos enteros, moneda y su forma decimal para mostrar. */
final class MontoResponse
{
    public function __construct(
        public readonly int $centavos,
        public readonly string $moneda,
        public readonly string $monto,
    ) {}

    public static function desde(Money $dinero): self
    {
        return new self($dinero->centavos(), $dinero->moneda(), $dinero->aDecimal());
    }

    public static function desdeOpcional(?Money $dinero): ?self
    {
        return $dinero === null ? null : self::desde($dinero);
    }
}
