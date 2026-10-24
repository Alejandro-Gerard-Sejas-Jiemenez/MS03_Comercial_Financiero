<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Acuerdos\Domain\MetodoCobro;
use Src\Acuerdos\Domain\MetodoCobroRepository;

final class InMemoryMetodoCobroRepository implements MetodoCobroRepository
{
    /** @var array<string, MetodoCobro> */
    private array $metodos = [];

    public function __construct(MetodoCobro ...$metodos)
    {
        foreach ($metodos as $metodo) {
            $this->guardar($metodo);
        }
    }

    public function buscar(string $metodoId): ?MetodoCobro
    {
        return $this->metodos[$metodoId] ?? null;
    }

    public function guardar(MetodoCobro $metodo): void
    {
        $this->metodos[$metodo->metodoId()] = $metodo;
    }

    public function deAnfitrion(string $anfitrionId): array
    {
        return array_values(array_filter(
            $this->metodos,
            static fn (MetodoCobro $metodo): bool => $metodo->anfitrionId() === $anfitrionId,
        ));
    }

    public function principalDe(string $anfitrionId): ?MetodoCobro
    {
        foreach ($this->metodos as $metodo) {
            if ($metodo->anfitrionId() === $anfitrionId && $metodo->esPrincipal()) {
                return $metodo;
            }
        }

        return null;
    }

    public function cantidad(): int
    {
        return count($this->metodos);
    }

    /** Todo lo guardado, para afirmar que nada quedo en texto claro. */
    public function todos(): array
    {
        return array_values($this->metodos);
    }
}
