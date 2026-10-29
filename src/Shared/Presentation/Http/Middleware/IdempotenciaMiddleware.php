<?php

declare(strict_types=1);

namespace Src\Shared\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header `Idempotency-Key`: misma clave y mismo cuerpo devuelven la respuesta
 * guardada sin ejecutar de nuevo; la misma clave con otro cuerpo es 422.
 * Uso: `idempotente` (opcional) o `idempotente:exigir` (obligatorio).
 */
final class IdempotenciaMiddleware
{
    public function handle(Request $request, Closure $next, string $modo = 'opcional'): Response
    {
        $clave = $request->header('Idempotency-Key');
        if ($clave === null || $clave === '') {
            if ($modo === 'exigir') {
                throw new ReglaDeNegocioViolada('Falta el header Idempotency-Key');
            }

            return $next($request);
        }

        $ruta = $request->method().' '.$request->path();
        $hash = hash('sha256', $request->getContent());

        $previa = DB::table('idempotencia_http')->where('clave', $clave)->where('ruta', $ruta)->first();
        if ($previa !== null) {
            if ($previa->hash_body !== $hash) {
                throw new ReglaDeNegocioViolada('La Idempotency-Key ya se uso con un cuerpo distinto');
            }

            return (new JsonResponse(json_decode((string) $previa->respuesta, true), (int) $previa->status))
                ->header('Idempotency-Replayed', 'true');
        }

        $respuesta = $next($request);

        if ($respuesta->getStatusCode() < 500) {
            DB::table('idempotencia_http')->insertOrIgnore([
                'clave' => $clave,
                'ruta' => $ruta,
                'hash_body' => $hash,
                'status' => $respuesta->getStatusCode(),
                'respuesta' => $respuesta->getContent() ?: 'null',
            ]);
        }

        return $respuesta;
    }
}
