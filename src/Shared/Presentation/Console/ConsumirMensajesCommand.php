<?php

declare(strict_types=1);

namespace Src\Shared\Presentation\Console;

use Illuminate\Console\Command;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Src\Shared\Presentation\Messaging\ConsumidorDeEventos;
use Throwable;

/**
 * Lee de RabbitMQ y le pasa cada mensaje al ConsumidorDeEventos (Inbox + manejadores).
 *
 * - `comercial.interno` se declara y se enlaza aqui a los eventos propios que
 *   MS-03 tambien consume (pago.capturado, reembolso.procesado).
 * - Las colas de MS-01 y MS-02 (`rabbitmq.colas_consumidas`) las enlaza a sus
 *   exchanges quien opera RabbitMQ; aqui solo se declaran durables.
 * - Un mensaje que falla se rechaza sin reencolar (cola de mensajes muertos),
 *   para que uno malo no bloquee a los demas.
 */
final class ConsumirMensajesCommand extends Command
{
    private const COLA_INTERNA = 'comercial.interno';

    protected $signature = 'mensajeria:consumir {--una-vez : procesa lo que hay y termina (para pruebas y cron)}';

    protected $description = 'Consume los eventos de RabbitMQ que MS-03 sabe manejar';

    public function handle(ConsumidorDeEventos $consumidor): int
    {
        $config = (array) config('comercial.rabbitmq');
        $conexion = AMQPConnectionFactory::create(
            (new AMQPConnectionConfig)
                ->setHost((string) $config['host'])
                ->setPort((int) $config['port'])
                ->setUser((string) $config['usuario'])
                ->setPassword((string) $config['password'])
                ->setIsLazy(true),
        );
        $canal = $conexion->channel();
        $canal->basic_qos(0, 10, false);

        $exchange = (string) $config['exchange'];
        $canal->exchange_declare($exchange, 'topic', false, true, false);
        $this->declararInterna($canal, $exchange, $consumidor->eventos());

        $colas = array_merge([self::COLA_INTERNA], (array) $config['colas_consumidas']);
        foreach ($colas as $cola) {
            if ($cola !== self::COLA_INTERNA) {
                $canal->queue_declare($cola, false, true, false, false);
            }
            $canal->basic_consume($cola, '', false, false, false, false, function (AMQPMessage $m) use ($consumidor, $cola): void {
                $this->atender($consumidor, $m, $cola);
            });
        }
        $this->info('Escuchando: '.implode(', ', $colas));

        if ($this->option('una-vez')) {
            try {
                $canal->wait(null, false, 2);
            } catch (AMQPTimeoutException) {
                // la cola esta vacia: nada mas que procesar
            }
        } else {
            while ($canal->is_consuming()) {
                $canal->wait();
            }
        }
        $canal->close();
        $conexion->close();

        return self::SUCCESS;
    }

    /** @param list<string> $eventos */
    private function declararInterna(AMQPChannel $canal, string $exchange, array $eventos): void
    {
        $canal->queue_declare(self::COLA_INTERNA, false, true, false, false);
        foreach (['pago.capturado', 'reembolso.procesado'] as $clave) {
            if (in_array($clave, $eventos, true)) {
                $canal->queue_bind(self::COLA_INTERNA, $exchange, $clave);
            }
        }
    }

    private function atender(ConsumidorDeEventos $consumidor, AMQPMessage $m, string $cola): void
    {
        try {
            $mensaje = json_decode($m->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $consumidor->procesar(
                (array) $mensaje,
                (string) ($mensaje['origen'] ?? $cola),
                $m->getRoutingKey(),
            );
            $m->ack();
        } catch (Throwable $e) {
            $this->error("{$cola}: {$e->getMessage()}");
            report($e);
            $m->nack(false);
        }
    }
}
