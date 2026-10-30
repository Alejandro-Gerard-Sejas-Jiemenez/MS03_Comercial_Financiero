<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Entity\Conciliacion;

use DateTimeImmutable;
use Src\Financiero\Domain\Entity\ConciliacionItem;
use Src\Financiero\Domain\Enum\EstadoConciliacion;
use Src\Financiero\Domain\Enum\ResultadoConciliacionItem;
use Src\Shared\Domain\Money;

/**
 * Lectura de los datos de la conciliacion.
 *
 * Parte de `Conciliacion`: usa sus campos privados.
 */
trait Lectura
{
    /** @return list<ConciliacionItem> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<ConciliacionItem> */
    public function itemsConDiferencia(): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (ConciliacionItem $item): bool => $item->resultado() !== ResultadoConciliacionItem::COINCIDE,
        ));
    }

    public function conciliacionId(): string
    {
        return $this->conciliacionId;
    }

    public function pasarela(): string
    {
        return $this->pasarela;
    }

    public function fechaCorte(): DateTimeImmutable
    {
        return $this->fechaCorte;
    }

    public function totalSistema(): Money
    {
        return $this->totalSistema;
    }

    public function totalPasarela(): Money
    {
        return $this->totalPasarela;
    }

    public function estado(): EstadoConciliacion
    {
        return $this->estado;
    }

    public function ejecutadaPor(): ?string
    {
        return $this->ejecutadaPor;
    }
}
