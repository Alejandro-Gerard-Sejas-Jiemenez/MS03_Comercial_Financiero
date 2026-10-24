<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Las entidades que son raiz de agregado acumulan sus eventos aqui.
 * El caso de uso los saca y los entrega al EventPublisher dentro de la
 * misma transaccion en que guarda el cambio de estado (patron Outbox).
 */
trait RegistraEventos
{
    /** @var list<DomainEvent> */
    private array $eventos = [];

    protected function registrar(DomainEvent $evento): void
    {
        $this->eventos[] = $evento;
    }

    /**
     * Devuelve los eventos pendientes y los descarta, para que no se publiquen dos veces.
     *
     * @return list<DomainEvent>
     */
    public function sacarEventos(): array
    {
        $eventos = $this->eventos;
        $this->eventos = [];

        return $eventos;
    }
}
