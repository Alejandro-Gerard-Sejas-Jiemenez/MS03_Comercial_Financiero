<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Src\Pagos\Domain\Comprobante;
use Src\Pagos\Domain\ComprobanteRepository;

final class InMemoryComprobanteRepository implements ComprobanteRepository
{
    /** @var array<string, Comprobante> */
    private array $comprobantes = [];

    public function buscar(string $comprobanteId): ?Comprobante
    {
        return $this->comprobantes[$comprobanteId] ?? null;
    }

    public function delPago(string $pagoId): ?Comprobante
    {
        foreach ($this->comprobantes as $comprobante) {
            if ($comprobante->pagoId() === $pagoId) {
                return $comprobante;
            }
        }

        return null;
    }

    public function guardar(Comprobante $comprobante): void
    {
        $this->comprobantes[$comprobante->comprobanteId()] = $comprobante;
    }
}
