<?php

declare(strict_types=1);

namespace Src\Shared\Adapters\Presenter;

use DateTimeInterface;
use Src\Shared\Application\Response\MontoResponse;

/**
 * Presentador (Clean Architecture: Interface Adapter). Convierte los modelos
 * Response de los casos de uso en el arreglo exacto que sale por HTTP.
 *
 * Cada modulo extiende esta clase y dice como presentar sus propios Response;
 * aqui solo estan los casos comunes (nulos, escalares, listas y montos).
 */
abstract class PresentadorBase
{
    public function presentar(mixed $respuesta): mixed
    {
        return match (true) {
            $respuesta === null, is_scalar($respuesta) => $respuesta,
            is_array($respuesta) => array_map($this->presentar(...), $respuesta),
            $respuesta instanceof MontoResponse => $this->monto($respuesta),
            is_object($respuesta) => $this->objeto($respuesta),
            default => null,
        };
    }

    abstract protected function objeto(object $respuesta): mixed;

    /** @return array{centavos: int, moneda: string, monto: string}|null */
    protected function monto(?MontoResponse $monto): ?array
    {
        return $monto === null ? null : [
            'centavos' => $monto->centavos,
            'moneda' => $monto->moneda,
            'monto' => $monto->monto,
        ];
    }

    protected function fecha(?DateTimeInterface $fecha): ?string
    {
        return $fecha?->format(DATE_ATOM);
    }
}
