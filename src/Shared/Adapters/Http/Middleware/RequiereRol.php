<?php

declare(strict_types=1);

namespace Src\Shared\Adapters\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Shared\Application\Auth\NoAutenticado;
use Src\Shared\Application\Auth\Sesion;
use Src\Shared\Domain\Exception\OperacionNoPermitida;
use Symfony\Component\HttpFoundation\Response;

/** Uso: `rol:ADMIN,ANFITRION`. Debe ir despues de `sesion`. */
final class RequiereRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $sesion = $request->attributes->get('sesion');
        if (! $sesion instanceof Sesion) {
            throw new NoAutenticado('Falta la sesion');
        }

        if (! $sesion->tieneAlgunRol(...$roles)) {
            throw new OperacionNoPermitida('Tu rol no puede realizar esta operacion');
        }

        return $next($request);
    }
}
