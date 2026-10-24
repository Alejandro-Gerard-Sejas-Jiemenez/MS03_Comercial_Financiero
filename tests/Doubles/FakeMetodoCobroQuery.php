<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Acuerdos\Application\Port\MetodoCobroQuery;

final class FakeMetodoCobroQuery implements MetodoCobroQuery
{
    /** @param array<string, string> $metodos anfitrion_id => metodo_id */
    public function __construct(private array $metodos = [])
    {
    }

    public function metodoPrincipalVerificado(string $anfitrionId): ?string
    {
        return $this->metodos[$anfitrionId] ?? null;
    }

    public function con(string $anfitrionId, string $metodoId): self
    {
        $this->metodos[$anfitrionId] = $metodoId;

        return $this;
    }
}
