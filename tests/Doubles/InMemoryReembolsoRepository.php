<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Pagos\Domain\Reembolso;
use Src\Pagos\Domain\ReembolsoRepository;

final class InMemoryReembolsoRepository implements ReembolsoRepository
{
    /** @var array<string, Reembolso> */
    private array $reembolsos = [];

    public function buscar(string $reembolsoId): ?Reembolso
    {
        return $this->reembolsos[$reembolsoId] ?? null;
    }

    public function guardar(Reembolso $reembolso): void
    {
        $this->reembolsos[$reembolso->reembolsoId()] = $reembolso;
    }

    public function delPago(string $pagoId): array
    {
        return array_values(array_filter(
            $this->reembolsos,
            static fn (Reembolso $r): bool => $r->pagoId() === $pagoId,
        ));
    }

    public function aprobadosPendientesDeProcesar(int $lote = 50): array
    {
        return [];
    }
}
