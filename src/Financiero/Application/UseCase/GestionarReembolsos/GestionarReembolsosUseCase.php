<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\GestionarReembolsos;

use Src\Financiero\Application\Response\ReembolsoResponse;

/**
 * Entrada del caso de uso GestionarReembolsos (Clean Architecture: Input Boundary).
 * Los adaptadores (controladores, consolas, suscriptores) dependen de esta interfaz, no del servicio.
 */
interface GestionarReembolsosUseCase
{
    public function solicitar(SolicitarReembolsoRequest $comando): ?ReembolsoResponse;

    public function revisar(AprobarReembolsoRequest $comando): ReembolsoResponse;

    public function procesar(ProcesarReembolsoRequest $comando): ReembolsoResponse;
}
