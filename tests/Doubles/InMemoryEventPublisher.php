<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Domain\DomainEvent;

final class InMemoryEventPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    private array $publicados = [];

    public function publicar(array $eventos): void
    {
        foreach ($eventos as $evento) {
            $this->publicados[] = $evento;
        }
    }

    /** @return list<DomainEvent> */
    public function publicados(): array
    {
        return $this->publicados;
    }

    /** @return list<string> */
    public function tipos(): array
    {
        return array_map(static fn (DomainEvent $e): string => $e->tipoEvento(), $this->publicados);
    }

    public function primero(): ?DomainEvent
    {
        return $this->publicados[0] ?? null;
    }

    public function contiene(string $tipoEvento): bool
    {
        return in_array($tipoEvento, $this->tipos(), true);
    }

    public function cantidad(): int
    {
        return count($this->publicados);
    }
}
