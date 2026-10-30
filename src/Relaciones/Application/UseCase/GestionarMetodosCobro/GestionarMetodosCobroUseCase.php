<?php

declare(strict_types=1);

namespace Src\Relaciones\Application\UseCase\GestionarMetodosCobro;

use Src\Relaciones\Application\Response\MetodoCobroResponse;

/**
 * Entrada del caso de uso GestionarMetodosCobro (Clean Architecture: Input Boundary).
 * Los adaptadores (controladores, consolas, suscriptores) dependen de esta interfaz, no del servicio.
 */
interface GestionarMetodosCobroUseCase
{
    public function registrar(RegistrarMetodoCobroRequest $comando): MetodoCobroResponse;

    public function actualizar(ActualizarMetodoCobroRequest $comando): MetodoCobroResponse;

    /** @return list<MetodoCobroResponse> */
    public function listarDe(string $anfitrionId): array;

    public function consultar(string $metodoId): MetodoCobroResponse;

    public function verificar(VerificarMetodoCobroRequest $comando): MetodoCobroResponse;
}
