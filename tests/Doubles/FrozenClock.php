<?php

declare(strict_types=1);

namespace Tests\Doubles;

use DateTimeImmutable;
use Src\Shared\Application\Port\Clock;

final class FrozenClock implements Clock
{
    private DateTimeImmutable $ahora;

    public function __construct(string $ahora = '2026-10-23 12:00:00')
    {
        $this->ahora = new DateTimeImmutable($ahora);
    }

    public function ahora(): DateTimeImmutable
    {
        return $this->ahora;
    }

    public function hoy(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->ahora->format('Y-m-d').' 00:00:00');
    }

    public function avanzarDias(int $dias): void
    {
        $this->ahora = $this->ahora->modify("+{$dias} days");
    }

    public function fijar(string $ahora): void
    {
        $this->ahora = new DateTimeImmutable($ahora);
    }
}
