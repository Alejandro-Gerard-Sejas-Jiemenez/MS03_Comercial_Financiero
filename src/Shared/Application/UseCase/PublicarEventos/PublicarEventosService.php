<?php

declare(strict_types=1);

namespace Src\Shared\Application\UseCase\PublicarEventos;

use RuntimeException;
use Src\Shared\Application\Port\MessageBroker;
use Src\Shared\Application\Port\OutboxRepository;

/** Caso de uso PublicarEventos: implementa la interfaz de entrada PublicarEventosUseCase. */
final class PublicarEventosService implements PublicarEventosUseCase
{
    public function __construct(
        private readonly OutboxRepository $outbox,
        private readonly MessageBroker $broker,
    ) {}

    /**
     * Disparador: job programado.
     *
     * Relay del patron Outbox: toma los `outbox_evento` con `publicado_en IS NULL`
     * y los envia a RabbitMQ. Se ejecuta aparte de la transaccion de negocio, por
     * eso el cambio de estado y su evento nunca quedan en desacuerdo.
     *
     * Entrega at-least-once: si el envio sale bien pero el proceso cae antes de
     * marcar la fila, el evento se reenvia. Los consumidores lo descartan con el
     * inbox (`evento_procesado`).
     */
    private const ORIGEN = 'MS-03';

    public function publicarPendientes(PublicarEventosOutboxRequest $comando): ResultadoPublicacion
    {
        $publicados = 0;
        $fallidos = 0;

        foreach ($this->outbox->pendientes($comando->lote) as $evento) {
            try {
                $this->broker->enviar($evento->tipoEvento, [
                    'id' => $evento->eventoId,
                    'tipo' => $evento->tipoEvento,
                    'origen' => self::ORIGEN,
                    'saga_id' => $evento->sagaId,
                    'agregado_tipo' => $evento->agregadoTipo,
                    'agregado_id' => $evento->agregadoId,
                    'data' => $evento->payload,
                ]);
            } catch (RuntimeException) {
                // Un broker caido no debe detener el lote ni perder el evento:
                // la fila sigue pendiente y se reintenta en la proxima corrida.
                $this->outbox->registrarIntento($evento->eventoId);
                $fallidos++;

                continue;
            }

            $this->outbox->marcarPublicado($evento->eventoId);
            $publicados++;
        }

        return new ResultadoPublicacion($publicados, $fallidos);
    }
}
