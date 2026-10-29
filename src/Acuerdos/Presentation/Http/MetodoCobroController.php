<?php

declare(strict_types=1);

namespace Src\Acuerdos\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Acuerdos\Application\UseCase\GestionarMetodosCobro\ActualizarMetodoCobroCommand;
use Src\Acuerdos\Application\UseCase\GestionarMetodosCobro\GestionarMetodosCobroService;
use Src\Acuerdos\Application\UseCase\GestionarMetodosCobro\RegistrarMetodoCobroCommand;
use Src\Acuerdos\Application\UseCase\VerificarMetodoCobro\VerificarMetodoCobroCommand;
use Src\Acuerdos\Application\UseCase\VerificarMetodoCobro\VerificarMetodoCobroHandler;
use Src\Acuerdos\Domain\TipoMetodoCobro;
use Src\Shared\Presentation\Http\ControladorBase;

/**
 * CU-ACU-03 y CU-ACU-04. El numero de cuenta entra por aqui pero el dominio lo
 * guarda cifrado y la respuesta solo muestra los ultimos 4 digitos.
 */
final class MetodoCobroController extends ControladorBase
{
    public function __construct(
        private readonly GestionarMetodosCobroService $metodos,
        private readonly VerificarMetodoCobroHandler $verificar,
    ) {}

    public function listar(Request $request, string $anfitrionId): JsonResponse
    {
        $this->exigirPropietario($request, $anfitrionId);

        return $this->responder($this->metodos->listarDe($anfitrionId));
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

        return $this->responder($this->metodos->registrar(new RegistrarMetodoCobroCommand(
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
        $metodo = $this->metodos->consultar($metodoId);
        $this->exigirPropietario($request, $metodo->anfitrionId());

        return $this->responder($metodo);
    }

    public function actualizar(Request $request, string $metodoId): JsonResponse
    {
        $d = $request->validate([
            'titular' => 'nullable|string|max:150',
            'banco' => 'nullable|string|max:100',
            'numero' => 'nullable|string|max:50',
        ]);
        $this->exigirPropietario($request, $this->metodos->consultar($metodoId)->anfitrionId());

        return $this->responder($this->metodos->actualizar(new ActualizarMetodoCobroCommand(
            metodoId: $metodoId,
            actualizadoPor: $this->sesion($request)->usuarioId,
            titular: $d['titular'] ?? null,
            banco: $d['banco'] ?? null,
            numero: $d['numero'] ?? null,
        )));
    }

    public function definirPrincipal(Request $request, string $metodoId): JsonResponse
    {
        $anfitrionId = $this->metodos->consultar($metodoId)->anfitrionId();
        $this->exigirPropietario($request, $anfitrionId);

        return $this->responder($this->metodos->definirPrincipal($metodoId, $anfitrionId));
    }

    public function verificar(Request $request, string $metodoId): JsonResponse
    {
        return $this->responder(($this->verificar)->manejar(new VerificarMetodoCobroCommand(
            $metodoId,
            $this->sesion($request)->usuarioId,
        )));
    }
}
