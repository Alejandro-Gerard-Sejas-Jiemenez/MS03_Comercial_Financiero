<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ConciliarTransacciones;

use Src\Financiero\Application\Response\ConciliacionResponse;

/**
 * Entrada del caso de uso ConciliarTransacciones (Clean Architecture: Input Boundary).
 * Los adaptadores (controladores, consolas, suscriptores) dependen de esta interfaz, no del servicio.
 */
interface ConciliarTransaccionesUseCase
{
    public function ejecutar(EjecutarConciliacionRequest $comando): ConciliacionResponse;

    public function revisarDiferencia(RevisarDiferenciaConciliacionRequest $comando): ConciliacionResponse;
}
