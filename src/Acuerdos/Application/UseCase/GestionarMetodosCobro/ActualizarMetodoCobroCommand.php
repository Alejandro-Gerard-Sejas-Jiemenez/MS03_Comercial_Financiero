<?php

declare(strict_types=1);

namespace Src\Acuerdos\Application\UseCase\GestionarMetodosCobro;

/**
 * Solo los campos no nulos se aplican. Si llega `numero`, se vuelve a cifrar y
 * el metodo pierde la verificacion: hay que verificar la cuenta nueva.
 */
final class ActualizarMetodoCobroCommand
{
    public function __construct(
        public readonly string $metodoId,
        public readonly string $actualizadoPor,
        public readonly ?string $titular = null,
        public readonly ?string $banco = null,
        public readonly ?string $numero = null,
    ) {
    }
}
