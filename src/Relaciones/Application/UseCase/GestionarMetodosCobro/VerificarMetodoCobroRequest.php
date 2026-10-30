<?php

declare(strict_types=1);

namespace Src\Relaciones\Application\UseCase\GestionarMetodosCobro;

/**
 * el Administrador confirma que la cuenta existe y es del
 * anfitrion. `verificadoPor` es su id (ref MS-01).
 */
final class VerificarMetodoCobroRequest
{
    public function __construct(
        public readonly string $metodoId,
        public readonly string $verificadoPor,
    ) {}
}
