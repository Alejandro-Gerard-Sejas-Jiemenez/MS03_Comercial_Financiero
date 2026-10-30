<?php

declare(strict_types=1);

namespace Src\Comercial\Application\UseCase\GestionarPromociones;

use Src\Comercial\Application\Response\PromocionResponse;
use Src\Comercial\Domain\Enum\EstadoPromocion;
use Src\Comercial\Domain\Enum\TipoPromocion;

/**
 * Entrada del caso de uso GestionarPromociones (Clean Architecture: Input Boundary).
 * Los adaptadores (controladores, consolas, suscriptores) dependen de esta interfaz, no del servicio.
 */
interface GestionarPromocionesUseCase
{
    public function crear(CrearPromocionRequest $comando): PromocionResponse;

    public function actualizar(ActualizarPromocionRequest $comando): PromocionResponse;

    public function asignarAlojamientos(AsignarAlojamientosRequest $comando): PromocionResponse;

    public function activar(string $promocionId, string $activadoPor): PromocionResponse;

    public function pausar(string $promocionId, string $pausadoPor): PromocionResponse;

    public function cancelar(string $promocionId, string $canceladoPor): PromocionResponse;

    /** @return list<PromocionResponse> */
    public function listar(?string $anfitrionId = null, ?EstadoPromocion $estado = null, ?TipoPromocion $tipo = null): array;

    public function consultar(string $promocionId): PromocionResponse;

    public function vencerPorFecha(VencerPromocionesRequest $comando): array;
}
