<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Messaging;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;

/**
 * Abre la conexion a RabbitMQ con la configuracion `comercial.rabbitmq`.
 * Los setters de AMQPConnectionConfig no se encadenan (devuelven void).
 */
final class ConexionRabbitMq
{
    /** @param array<string, mixed> $config */
    public static function desde(array $config): AbstractConnection
    {
        $c = new AMQPConnectionConfig;
        $c->setHost((string) $config['host']);
        $c->setPort((int) $config['port']);
        $c->setUser((string) $config['usuario']);
        $c->setPassword((string) $config['password']);
        $c->setIsLazy(true);

        return AMQPConnectionFactory::create($c);
    }
}
