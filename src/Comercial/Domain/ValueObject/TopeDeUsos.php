<?php

declare(strict_types=1);

namespace Src\Comercial\Domain\ValueObject;

use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/**
 * Cuantas veces puede usarse una promocion (`max_usos`, NULL = ilimitado) y
 * cuantas lleva (`usos_actuales`). CHECK `chk_promocion_usos`: nunca se pasa del tope.
 */
final class TopeDeUsos
{
    private function __construct(
        private readonly ?int $maximo,
        private readonly int $actuales,
    ) {}

    /** Tope de una promocion nueva: todavia sin usos. */
    public static function crear(?int $maximo): self
    {
        return new self(self::validar($maximo, 0), 0);
    }

    /** Lo que ya esta guardado: no se revalida. */
    public static function reconstituir(?int $maximo, int $actuales): self
    {
        return new self($maximo, $actuales);
    }

    /** Cambia el tope sin perder los usos ya registrados; null lo deja ilimitado. */
    public function conMaximo(?int $maximo): self
    {
        return new self(self::validar($maximo, $this->actuales), $this->actuales);
    }

    public function hayDisponibles(): bool
    {
        return $this->maximo === null || $this->actuales < $this->maximo;
    }

    /** El llamador comprueba antes `hayDisponibles()`: aqui solo se cuenta. */
    public function registrarUno(): self
    {
        return new self($this->maximo, $this->actuales + 1);
    }

    public function maximo(): ?int
    {
        return $this->maximo;
    }

    public function actuales(): int
    {
        return $this->actuales;
    }

    private static function validar(?int $maximo, int $actuales): ?int
    {
        if ($maximo === null) {
            return null;
        }

        if ($maximo < 1) {
            throw new ReglaDeNegocioViolada("El tope de usos debe ser al menos 1, recibido {$maximo}");
        }

        if ($maximo < $actuales) {
            throw new ReglaDeNegocioViolada(
                "El tope de usos ({$maximo}) no puede ser menor que los {$actuales} usos ya registrados",
            );
        }

        return $maximo;
    }
}
