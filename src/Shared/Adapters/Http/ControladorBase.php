<?php

declare(strict_types=1);

namespace Src\Shared\Adapters\Http;

use BackedEnum;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Shared\Adapters\Presenter\PresentadorBase;
use Src\Shared\Application\Auth\NoAutenticado;
use Src\Shared\Application\Auth\Sesion;
use Src\Shared\Domain\Exception\OperacionNoPermitida;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/**
 * Utilidades de los controladores. Aqui no hay reglas de negocio: solo leer la
 * sesion, convertir tipos de la peticion y dar forma a la respuesta.
 */
abstract class ControladorBase
{
    public const ADMIN = 'ADMIN';

    public const ANFITRION = 'ANFITRION';

    public const CLIENTE = 'CLIENTE';

    protected function sesion(Request $request): Sesion
    {
        $sesion = $request->attributes->get('sesion');
        if (! $sesion instanceof Sesion) {
            throw new NoAutenticado('Falta la sesion');
        }

        return $sesion;
    }

    protected function esAdmin(Request $request): bool
    {
        return $this->sesion($request)->tieneAlgunRol(self::ADMIN);
    }

    /** El ANFITRION solo toca lo suyo; el ADMIN toca todo. */
    protected function exigirPropietario(Request $request, ?string $duenoId): void
    {
        if ($this->esAdmin($request)) {
            return;
        }
        if ($duenoId === null || $duenoId !== $this->sesion($request)->usuarioId) {
            throw new OperacionNoPermitida('Este recurso no es tuyo');
        }
    }

    /** Presentador del modulo del controlador: sabe dar forma a sus Response. */
    abstract protected function presentador(): PresentadorBase;

    /** @return JsonResponse `{data: ...}` con el Response del caso de uso ya presentado */
    protected function responder(mixed $dato, int $status = 200): JsonResponse
    {
        return new JsonResponse(['data' => $this->presentador()->presentar($dato)], $status);
    }

    protected function fecha(?string $texto): ?DateTimeImmutable
    {
        if ($texto === null || $texto === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($texto);
        } catch (\Exception) {
            throw new ReglaDeNegocioViolada("Fecha invalida: {$texto}");
        }
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    protected function enum(string $enum, ?string $valor): ?BackedEnum
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return $enum::tryFrom($valor)
            ?? throw new ReglaDeNegocioViolada("Valor invalido '{$valor}' para ".class_basename($enum));
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T
     */
    protected function enumObligatorio(string $enum, ?string $valor): BackedEnum
    {
        return $this->enum($enum, $valor)
            ?? throw new ReglaDeNegocioViolada('Falta un valor de '.class_basename($enum));
    }
}
