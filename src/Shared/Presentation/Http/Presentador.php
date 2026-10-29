<?php

declare(strict_types=1);

namespace Src\Shared\Presentation\Http;

use BackedEnum;
use DateTimeInterface;
use ReflectionObject;
use Src\Shared\Domain\Money;

/**
 * Convierte entidades y objetos de valor del dominio en arreglos JSON.
 *
 * Lee las propiedades (no llama a ningun metodo, asi que no puede disparar un
 * cambio de estado) y nunca expone lo que el dominio guarda cifrado ni su
 * lista interna de eventos.
 */
final class Presentador
{
    /** Propiedades que jamas salen por HTTP. */
    private const OCULTAS = ['eventos'];

    public static function aArreglo(mixed $valor): mixed
    {
        return match (true) {
            $valor === null, is_scalar($valor) => $valor,
            $valor instanceof Money => [
                'centavos' => $valor->centavos(),
                'moneda' => $valor->moneda(),
                'monto' => $valor->aDecimal(),
            ],
            $valor instanceof BackedEnum => $valor->value,
            $valor instanceof DateTimeInterface => $valor->format(DATE_ATOM),
            is_array($valor) => array_map(self::aArreglo(...), $valor),
            is_object($valor) => self::deObjeto($valor),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private static function deObjeto(object $objeto): array
    {
        $salida = [];
        foreach ((new ReflectionObject($objeto))->getProperties() as $propiedad) {
            $nombre = $propiedad->getName();
            if (in_array($nombre, self::OCULTAS, true) || str_contains(strtolower($nombre), 'cifrado')) {
                continue;
            }
            if (! $propiedad->isInitialized($objeto)) {
                continue;
            }

            $salida[self::aSnakeCase($nombre)] = self::aArreglo($propiedad->getValue($objeto));
        }

        return $salida;
    }

    private static function aSnakeCase(string $nombre): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $nombre));
    }
}
