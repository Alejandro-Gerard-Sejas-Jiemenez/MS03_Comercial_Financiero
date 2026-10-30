<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Referencia;

/**
 * Copia local de lo minimo que MS-03 necesita de un anfitrion de MS-01.
 *
 * Existe porque Database per Service prohibe joins y llaves foraneas entre
 * microservicios: sin esta copia, liquidar de noche obligaria a preguntarle a
 * MS-01 por REST el estado de cada anfitrion, y si MS-01 esta caido la
 * liquidacion falla o se atrasa.
 */
final class AnfitrionReference
{
    public function __construct(
        public readonly string $anfitrionId,
        public readonly string $nombreCompleto,
        public readonly EstadoVerificacionAnfitrion $estadoVerificacion,
        public readonly ?string $nit = null,
        public readonly ?string $razonSocial = null,
    ) {}

    public function estaVerificado(): bool
    {
        return $this->estadoVerificacion === EstadoVerificacionAnfitrion::VERIFICADO;
    }

    /**
     * Un anfitrion suspendido o bloqueado no recibe pagos: sus liquidaciones
     * quedan retenidas.
     */
    public function puedeRecibirLiquidaciones(): bool
    {
        return $this->estaVerificado();
    }
}
