<?php

declare(strict_types=1);

namespace Src\Monetizacion\Application\UseCase\GestionarReglasMonetizacion;

use DateTimeImmutable;
use Src\Monetizacion\Application\Response\ReglaMonetizacionResponse;
use Src\Monetizacion\Domain\Enum\Concepto;
use Src\Monetizacion\Domain\Enum\EstadoRegla;

/**
 * Entrada del caso de uso GestionarReglasMonetizacion (Clean Architecture: Input Boundary).
 * Los adaptadores (controladores, consolas, suscriptores) dependen de esta interfaz, no del servicio.
 */
interface GestionarReglasMonetizacionUseCase
{
    public function crear(CrearReglaRequest $comando): ReglaMonetizacionResponse;

    public function actualizar(ActualizarReglaRequest $comando): ReglaMonetizacionResponse;

    public function desactivar(string $reglaId, string $desactivadoPor): ReglaMonetizacionResponse;

    /** @return list<ReglaMonetizacionResponse> */
    public function listar(?Concepto $concepto = null, ?EstadoRegla $estado = null): array;

    /** @return list<ReglaMonetizacionResponse> */
    public function vigentes(Concepto $concepto, ?DateTimeImmutable $fecha = null, ?string $categoriaAlojamiento = null, ?string $nivelAnfitrion = null): array;

    public function consultar(string $reglaId): ReglaMonetizacionResponse;
}
