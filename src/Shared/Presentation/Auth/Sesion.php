<?php

declare(strict_types=1);

namespace Src\Shared\Presentation\Auth;

/**
 * Quien hace la peticion, ya verificado. `usuarioId` es el `sub` del token y
 * coincide con `anfitrion_id` / `cliente_id` de MS-03.
 */
final class Sesion
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public readonly string $usuarioId,
        public readonly array $roles,
    ) {}

    public function tieneAlgunRol(string ...$roles): bool
    {
        return array_intersect($roles, $this->roles) !== [];
    }
}
