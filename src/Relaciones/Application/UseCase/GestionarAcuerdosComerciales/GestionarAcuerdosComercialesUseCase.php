<?php

declare(strict_types=1);

namespace Src\Relaciones\Application\UseCase\GestionarAcuerdosComerciales;

use Src\Relaciones\Application\Response\AcuerdoComercialResponse;
use Src\Relaciones\Domain\Enum\EstadoAcuerdo;

/**
 * Entrada del caso de uso GestionarAcuerdosComerciales (Clean Architecture: Input Boundary).
 * Los adaptadores (controladores, consolas, suscriptores) dependen de esta interfaz, no del servicio.
 */
interface GestionarAcuerdosComercialesUseCase
{
    public function crear(CrearAcuerdoBorradorRequest $comando): AcuerdoComercialResponse;

    public function actualizarCondiciones(ActualizarCondicionesAcuerdoRequest $comando): AcuerdoComercialResponse;

    /** @return list<AcuerdoComercialResponse> */
    public function listar(?EstadoAcuerdo $estado = null, ?string $anfitrionId = null): array;

    public function consultar(string $acuerdoId): AcuerdoComercialResponse;

    public function cambiarEstado(CambiarEstadoAcuerdoRequest $comando): AcuerdoComercialResponse;

    /** @return list<string> ids de los acuerdos finalizados por vencimiento */
    public function finalizarVencidos(): array;
}
