<?php

declare(strict_types=1);

namespace Src\Financiero\Application\Port;

use Src\Financiero\Domain\Entity\Reembolso;

interface ReembolsoRepository
{
    public function buscar(string $reembolsoId): ?Reembolso;

    public function guardar(Reembolso $reembolso): void;

    /** @return list<Reembolso> */
    public function delPago(string $pagoId): array;
}
