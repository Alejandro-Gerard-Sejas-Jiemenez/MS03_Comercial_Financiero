<?php

declare(strict_types=1);

namespace Src\Shared\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Shared\Presentation\Auth\AutenticadorDeSolicitud;
use Symfony\Component\HttpFoundation\Response;

/** Exige un token valido y deja la `Sesion` en `$request->attributes['sesion']`. */
final class AutenticarSesion
{
    public function __construct(private readonly AutenticadorDeSolicitud $autenticador) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('sesion', $this->autenticador->autenticar($request->bearerToken()));

        return $next($request);
    }
}
