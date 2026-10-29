<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Messaging;

use Closure;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Src\Shared\Application\Port\MessageBroker;
use Throwable;

/**
 * Publica en el exchange topic de MS-03 (`comercial.events`) con php-amqplib.
 *
 * La conexion es perezosa: el canal se abre al primer mensaje y se reutiliza
 * durante el lote del relay. Cualquier fallo se relanza como RuntimeException
 * para que el relay del Outbox deje el evento pendiente y lo reintente.
 */
final class RabbitMqMessageBroker implements MessageBroker
{
    private ?AMQPChannel $canal = null;

    /**
     * @param  Closure():AMQPChannel  $abrirCanal  abre un canal nuevo (inyectable para pruebas)
     */
    public function __construct(
        private readonly Closure $abrirCanal,
        private readonly string $exchange,
    ) {}

    public function enviar(string $routingKey, array $mensaje): void
    {
        try {
            $canal = $this->canal ??= $this->preparar();
            $canal->basic_publish(
                new AMQPMessage(
                    json_encode($mensaje, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    [
                        'content_type' => 'application/json',
                        'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                        'message_id' => (string) ($mensaje['id'] ?? ''),
                        'type' => $routingKey,
                    ],
                ),
                $this->exchange,
                $routingKey,
            );
        } catch (Throwable $e) {
            $this->canal = null; // la proxima vez se reconecta
            throw new RuntimeException("No se pudo publicar {$routingKey} en RabbitMQ: {$e->getMessage()}", 0, $e);
        }
    }

    private function preparar(): AMQPChannel
    {
        $canal = ($this->abrirCanal)();
        $canal->exchange_declare($this->exchange, 'topic', false, true, false);

        return $canal;
    }
}
