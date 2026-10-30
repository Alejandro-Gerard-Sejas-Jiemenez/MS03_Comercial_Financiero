<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\GestionarLiquidaciones;

use Src\Financiero\Application\Response\LiquidacionResponse;

/**
 * Entrada del caso de uso GestionarLiquidaciones (Clean Architecture: Input Boundary).
 * Los adaptadores (controladores, consolas, suscriptores) dependen de esta interfaz, no del servicio.
 */
interface GestionarLiquidacionesUseCase
{
    public function marcarLiquidables(MarcarComisionesLiquidablesRequest $comando): array;

    /** @return list<LiquidacionResponse> */
    public function generar(GenerarLiquidacionesRequest $comando): array;

    public function aprobar(AprobarLiquidacionRequest $comando): LiquidacionResponse;

    public function rechazar(RechazarLiquidacionRequest $comando): LiquidacionResponse;

    public function pagar(PagarLiquidacionRequest $comando): LiquidacionResponse;

    public function retener(RetenerLiquidacionesDeAnfitrionRequest $comando): array;
}
