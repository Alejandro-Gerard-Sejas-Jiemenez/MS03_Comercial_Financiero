<?php

declare(strict_types=1);

namespace Src\Relaciones\Application\UseCase\GestionarMetodosCobro;

/**
 * Solo los campos no nulos se aplican. Si llega `numero`, se vuelve a cifrar y
 * el metodo pierde la verificacion: hay que verificar la cuenta nueva.
 */
final class ActualizarMetodoCobroRequest
{
    public function __construct(
        public readonly string $metodoId,
        public readonly string $actualizadoPor,
        public readonly ?string $titular = null,
        public readonly ?string $banco = null,
        public readonly ?string $numero = null,
        public readonly ?bool $esPrincipal = null,
        public readonly ?bool $activo = null,
    ) {}
}
