<?php

declare(strict_types=1);

namespace Src\Shared\Adapters\Messaging;

use Src\Shared\Application\Port\InboxRegistry;
use Src\Shared\Application\Port\TransactionManager;

/**
 * Recibe un mensaje ya decodificado, descarta los repetidos con el Inbox y se
 * lo da a quien lo sepa manejar. No conoce RabbitMQ: el comando Artisan le
 * pasa lo que llega de la cola, y las pruebas lo llaman directo.
 *
 * Los manejadores y la marca del Inbox van en una sola transaccion: si algo
 * falla no queda marcado y el mensaje se puede reintentar sin perder nada.
 */
final class ConsumidorDeEventos
{
    /** @var array<string, list<callable>> */
    private array $manejadores = [];

    /**
     * @param  iterable<SuscriptorDeEventos>  $suscriptores  en orden: el primero corre primero
     */
    public function __construct(
        iterable $suscriptores,
        private readonly InboxRegistry $inbox,
        private readonly TransactionManager $tx,
    ) {
        foreach ($suscriptores as $suscriptor) {
            foreach ($suscriptor->manejadores() as $evento => $manejador) {
                $this->manejadores[$evento][] = $manejador;
            }
        }
    }

    /** @return list<string> eventos que alguien sabe manejar (para enlazar las colas) */
    public function eventos(): array
    {
        return array_keys($this->manejadores);
    }

    /**
     * @param  array<string, mixed>  $mensaje  `{id, tipo, data, ...}` como lo publica el Outbox
     * @return bool true si se proceso; false si se descarto (repetido o sin manejador)
     */
    public function procesar(array $mensaje, string $origen, ?string $routingKey = null): bool
    {
        $id = $mensaje['id'] ?? null;
        $tipo = (string) ($mensaje['tipo'] ?? $routingKey ?? '');
        if (! is_string($id) || $id === '') {
            throw new \InvalidArgumentException('El mensaje no trae id: no se puede deduplicar');
        }
        if (! isset($this->manejadores[$tipo])) {
            return false;
        }
        if ($this->inbox->yaProcesado($id)) {
            return false;
        }

        $data = (array) ($mensaje['data'] ?? []);
        $this->tx->ejecutar(function () use ($tipo, $data, $mensaje, $id, $origen): void {
            foreach ($this->manejadores[$tipo] as $manejador) {
                $manejador($data, $mensaje);
            }
            $this->inbox->marcar($id, $tipo, $origen);
        });

        return true;
    }
}
