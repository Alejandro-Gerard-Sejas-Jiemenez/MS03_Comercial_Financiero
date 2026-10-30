<?php

declare(strict_types=1);

namespace Src\Shared\Presentation\Http;

use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\OperacionNoPermitida;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Presentation\Auth\NoAutenticado;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Traduce excepciones a `{codigo, mensaje}` en las rutas `api/*`.
 * Lo que no reconoce lo deja al manejador de Laravel (500).
 */
final class ManejadorDeExcepciones
{
    public static function registrar(Exceptions $exceptions): void
    {
        $exceptions->render(static function (Throwable $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return self::aRespuesta($e);
        });
    }

    public static function aRespuesta(Throwable $e): ?JsonResponse
    {
        return match (true) {
            $e instanceof NoAutenticado => self::json(401, 'no_autenticado', $e->getMessage()),
            $e instanceof OperacionNoPermitida => self::json(403, 'operacion_no_permitida', $e->getMessage()),
            $e instanceof RecursoNoEncontrado => self::json(404, 'no_encontrado', $e->getMessage()),
            $e instanceof ConflictoDeEstado => self::json(409, 'conflicto', $e->getMessage()),
            $e instanceof DomainException => self::json(422, 'regla_de_negocio', $e->getMessage()),
            $e instanceof InvalidArgumentException => self::json(422, 'dato_invalido', $e->getMessage()),
            $e instanceof ValidationException => self::json(422, 'validacion', (string) collect($e->errors())->flatten()->first()),
            // La base es la ultima defensa: una clave foranea o unica rota es un conflicto del cliente, no un 500.
            $e instanceof QueryException && (string) $e->getCode() === '23503' => self::json(422, 'referencia_inexistente', 'Falta una copia local necesaria (anfitrion o reserva sincronizados desde MS-01/MS-02)'),
            $e instanceof QueryException && (string) $e->getCode() === '23505' => self::json(409, 'conflicto', 'Ya existe un registro con esos datos'),
            $e instanceof BadRequestHttpException => self::json(400, 'solicitud_invalida', $e->getMessage()),
            $e instanceof NotFoundHttpException => self::json(404, 'ruta_no_encontrada', 'La ruta no existe'),
            default => null,
        };
    }

    private static function json(int $status, string $codigo, string $mensaje): JsonResponse
    {
        return new JsonResponse(['codigo' => $codigo, 'mensaje' => $mensaje], $status);
    }
}
