<?php

declare(strict_types=1);

namespace Tests\Doubles;

use RuntimeException;
use Src\Shared\Application\Port\MessageBroker;

final class FakeMessageBroker implements MessageBroker
{
    /** @var list<array{routingKey: string, mensaje: array<string, mixed>}> */
    private array $enviados = [];

    private bool $caido = false;

    public function enviar(string $routingKey, array $mensaje): void
    {
        if ($this->caido) {
            throw new RuntimeException('broker no disponible');
        }

        $this->enviados[] = ['routingKey' => $routingKey, 'mensaje' => $mensaje];
    }

    public function caer(): void
    {
        $this->caido = true;
    }

    /** @return list<array{routingKey: string, mensaje: array<string, mixed>}> */
    public function enviados(): array
    {
        return $this->enviados;
    }
}
