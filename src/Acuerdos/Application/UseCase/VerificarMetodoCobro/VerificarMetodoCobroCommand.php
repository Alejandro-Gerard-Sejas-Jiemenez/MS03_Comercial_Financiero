<?php

declare(strict_types=1);

namespace Src\Acuerdos\Application\UseCase\VerificarMetodoCobro;

/**
 * CU-ACU-04: el Administrador confirma que la cuenta existe y es del
 * anfitrion. `verificadoPor` es su id (ref MS-01).
 */
final class VerificarMetodoCobroCommand
{
    public function __construct(
        public readonly string $metodoId,
        public readonly string $verificadoPor,
    ) {}
}
