<?php

declare(strict_types=1);

namespace Src\Comercial\Domain\ValueObject;

use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/**
 * Codigo de un cupon (`promocion.codigo`, VARCHAR(30) UNIQUE): siempre en
 * mayusculas, sin espacios en los extremos, no vacio y de hasta 30 caracteres.
 */
final class CodigoCupon
{
    private const LARGO_MAXIMO = 30;

    private function __construct(private readonly string $valor) {}

    /** Valida y normaliza lo que escribe una persona. */
    public static function desde(string $codigo): self
    {
        $limpio = strtoupper(trim($codigo));

        if ($limpio === '') {
            throw new ReglaDeNegocioViolada('El codigo de cupon no puede estar vacio');
        }

        if (mb_strlen($limpio) > self::LARGO_MAXIMO) {
            throw new ReglaDeNegocioViolada('El codigo de cupon no puede pasar de '.self::LARGO_MAXIMO." caracteres: {$codigo}");
        }

        return new self($limpio);
    }

    /** Lo que ya esta guardado: no se revalida. */
    public static function reconstituir(string $codigo): self
    {
        return new self($codigo);
    }

    public function valor(): string
    {
        return $this->valor;
    }
}
