<?php

declare(strict_types=1);

namespace Src\Shared\Application\UseCase\PublicarEventos;

/**
 * Entrada del caso de uso PublicarEventos (Clean Architecture: Input Boundary).
 * Los adaptadores (controladores, consolas, suscriptores) dependen de esta interfaz, no del servicio.
 */
interface PublicarEventosUseCase
{
    public function publicarPendientes(PublicarEventosOutboxRequest $comando): ResultadoPublicacion;
}
