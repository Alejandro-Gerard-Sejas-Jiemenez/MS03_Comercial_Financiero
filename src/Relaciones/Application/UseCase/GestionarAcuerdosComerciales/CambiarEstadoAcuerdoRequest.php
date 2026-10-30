<?php

declare(strict_types=1);

namespace Src\Relaciones\Application\UseCase\GestionarAcuerdosComerciales;

use Src\Relaciones\Domain\Enum\EstadoAcuerdo;

/**
 * . Un solo comando para las cuatro transiciones (activar, suspender,
 * finalizar, rescindir), porque todas son la misma intencion de negocio:
 * mover el estado del acuerdo y avisarle a MS-01.
 *
 * `sagaId` llega cuando el disparador es un evento de RabbitMQ y hay que
 * correlacionar con la saga de reserva de MS-02.
 */
final class CambiarEstadoAcuerdoRequest
{
    public function __construct(
        public readonly string $acuerdoId,
        public readonly EstadoAcuerdo $estadoDestino,
        public readonly string $solicitadoPor,
        public readonly ?string $motivo = null,
        public readonly ?string $sagaId = null,
    ) {}
}
