<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\GestionarComisiones;

use Src\Financiero\Application\Response\ComisionResponse;
use Src\Financiero\Application\Response\ResultadoReversionResponse;

/**
 * Entrada del caso de uso GestionarComisiones (Clean Architecture: Input Boundary).
 * Los adaptadores (controladores, consolas, suscriptores) dependen de esta interfaz, no del servicio.
 */
interface GestionarComisionesUseCase
{
    public function registrar(RegistrarComisionRequest $comando): ComisionResponse;

    public function revertir(RevertirComisionRequest $comando): ResultadoReversionResponse;
}
