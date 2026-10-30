<?php

declare(strict_types=1);

namespace Src\Relaciones\Adapters\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Relaciones\Adapters\Presenter\RelacionesPresentador;
use Src\Relaciones\Application\UseCase\GestionarMetodosCobro\ActualizarMetodoCobroRequest;
use Src\Relaciones\Application\UseCase\GestionarMetodosCobro\GestionarMetodosCobroUseCase;
use Src\Relaciones\Application\UseCase\GestionarMetodosCobro\RegistrarMetodoCobroRequest;
use Src\Relaciones\Application\UseCase\GestionarMetodosCobro\VerificarMetodoCobroRequest;
use Src\Relaciones\Domain\Enum\TipoMetodoCobro;
use Src\Shared\Adapters\Http\ControladorBase;
use Src\Shared\Adapters\Presenter\PresentadorBase;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/**
 * . El numero de cuenta entra por aqui pero el dominio lo
 * guarda cifrado y la respuesta solo muestra los ultimos 4 digitos.
 */
final class MetodoCobroController extends ControladorBase
{
    protected function presentador(): PresentadorBase
    {
        return new RelacionesPresentador;
    }

    public function __construct(
        private readonly GestionarMetodosCobroUseCase $gestionarMetodosCobro,
    ) {}

    public function listar(Request $request, string $anfitrionId): JsonResponse
    {
        $this->exigirPropietario($request, $anfitrionId);

        return $this->responder($this->gestionarMetodosCobro->listarDe($anfitrionId));
    }

    public function registrar(Request $request, string $anfitrionId): JsonResponse
    {
        $d = $request->validate([
            'tipo' => 'required|string',
            'titular' => 'required|string|max:150',
            'numero' => 'required|string|max:50',
            'banco' => 'nullable|string|max:100',
            'es_principal' => 'nullable|boolean',
        ]);
        $this->exigirPropietario($request, $anfitrionId);

        return $this->responder($this->gestionarMetodosCobro->registrar(new RegistrarMetodoCobroRequest(
            anfitrionId: $anfitrionId,
            tipo: $this->enumObligatorio(TipoMetodoCobro::class, $d['tipo']),
            titular: $d['titular'],
            numero: $d['numero'],
            registradoPor: $this->sesion($request)->usuarioId,
            banco: $d['banco'] ?? null,
            esPrincipal: (bool) ($d['es_principal'] ?? false),
        )), 201);
    }

    public function mostrar(Request $request, string $metodoId): JsonResponse
    {
        $metodo = $this->gestionarMetodosCobro->consultar($metodoId);
        $this->exigirPropietario($request, $metodo->anfitrionId);

        return $this->responder($metodo);
    }

    /**
     * PUT: actualiza lo que se envia. `es_principal` elige el metodo principal y
     * `activo: false` lo da de baja (no hay DELETE); `activo` viaja solo.
     */
    public function actualizar(Request $request, string $metodoId): JsonResponse
    {
        $d = $request->validate([
            'titular' => 'nullable|string|max:150',
            'banco' => 'nullable|string|max:100',
            'numero' => 'nullable|string|max:50',
            'es_principal' => 'nullable|boolean',
            'activo' => 'nullable|boolean',
        ]);
        $this->exigirPropietario($request, $this->gestionarMetodosCobro->consultar($metodoId)->anfitrionId);
        if (isset($d['activo']) && array_diff(array_keys(array_filter($d, static fn (mixed $v): bool => $v !== null)), ['activo']) !== []) {
            throw new ReglaDeNegocioViolada('El estado activo se cambia solo: no lo mandes junto con otros campos');
        }

        return $this->responder($this->gestionarMetodosCobro->actualizar(new ActualizarMetodoCobroRequest(
            metodoId: $metodoId,
            actualizadoPor: $this->sesion($request)->usuarioId,
            titular: $d['titular'] ?? null,
            banco: $d['banco'] ?? null,
            numero: $d['numero'] ?? null,
            esPrincipal: isset($d['es_principal']) ? (bool) $d['es_principal'] : null,
            activo: isset($d['activo']) ? (bool) $d['activo'] : null,
        )));
    }

    public function verificar(Request $request, string $metodoId): JsonResponse
    {
        return $this->responder($this->gestionarMetodosCobro->verificar(new VerificarMetodoCobroRequest(
            $metodoId,
            $this->sesion($request)->usuarioId,
        )));
    }
}
