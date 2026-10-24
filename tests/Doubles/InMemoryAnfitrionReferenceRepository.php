<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Referencias\Domain\AnfitrionReference;
use Src\Referencias\Domain\AnfitrionReferenceRepository;

final class InMemoryAnfitrionReferenceRepository implements AnfitrionReferenceRepository
{
    /** @var array<string, AnfitrionReference> */
    private array $anfitriones = [];

    public function __construct(AnfitrionReference ...$anfitriones)
    {
        foreach ($anfitriones as $anfitrion) {
            $this->guardar($anfitrion);
        }
    }

    public function buscar(string $anfitrionId): ?AnfitrionReference
    {
        return $this->anfitriones[$anfitrionId] ?? null;
    }

    public function guardar(AnfitrionReference $anfitrion): void
    {
        $this->anfitriones[$anfitrion->anfitrionId] = $anfitrion;
    }

    public function verificados(): array
    {
        return array_values(array_filter(
            $this->anfitriones,
            static fn (AnfitrionReference $a): bool => $a->estaVerificado(),
        ));
    }

    public function cantidad(): int
    {
        return count($this->anfitriones);
    }
}
