<?php

declare(strict_types=1);

namespace Src\Shared\Application\UseCase\SincronizarReferencias;

/**
 * Entrada del caso de uso SincronizarReferencias (Clean Architecture: Input Boundary).
 * Los adaptadores (controladores, consolas, suscriptores) dependen de esta interfaz, no del servicio.
 */
interface SincronizarReferenciasUseCase
{
    public function sincronizarAnfitrion(SincronizarAnfitrionRequest $comando): void;

    public function sincronizarReserva(SincronizarReservaRequest $comando): void;
}
