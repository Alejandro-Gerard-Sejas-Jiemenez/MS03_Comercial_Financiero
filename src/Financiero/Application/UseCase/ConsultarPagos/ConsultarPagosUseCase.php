<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\ConsultarPagos;

use Src\Financiero\Application\Response\DetalleDePagoResponse;
use Src\Financiero\Application\Response\PagoResponse;

/**
 * Entrada del caso de uso ConsultarPagos (Clean Architecture: Input Boundary).
 * Los adaptadores (controladores, consolas, suscriptores) dependen de esta interfaz, no del servicio.
 */
interface ConsultarPagosUseCase
{
    public function detalle(string $pagoId): DetalleDePagoResponse;

    /** @return list<PagoResponse> */
    public function listar(ListarPagosRequest $comando): array;
}
