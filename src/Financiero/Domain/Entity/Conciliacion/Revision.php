<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Entity\Conciliacion;

use Src\Financiero\Domain\Enum\EstadoConciliacion;
use Src\Financiero\Domain\Enum\ResultadoConciliacionItem;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;

/**
 * Revision de los items de la conciliacion y su cierre.
 *
 * Parte de `Conciliacion`: usa sus campos privados.
 */
trait Revision
{
    /** Columna generada: `total_pasarela - total_sistema`. */
    public function diferencia(): Money
    {
        return $this->totalPasarela->menos($this->totalSistema);
    }

    public function todoCuadra(): bool
    {
        if (! $this->diferencia()->esCero()) {
            return false;
        }

        foreach ($this->items as $item) {
            if ($item->resultado() !== ResultadoConciliacionItem::COINCIDE) {
                return false;
            }
        }

        return true;
    }

    /**
     * el Administrador explica un item con diferencia y, si hace
     * falta, lo liga a la transaccion correcta.
     */
    public function revisarItem(
        string $referenciaPasarela,
        string $observacion,
        ?string $transaccionId = null,
    ): void {
        if (trim($observacion) === '') {
            throw new ReglaDeNegocioViolada('La revision necesita una observacion');
        }

        foreach ($this->items as $item) {
            if ($item->referenciaPasarela() === $referenciaPasarela) {
                $item->revisar($observacion, $transaccionId);

                return;
            }
        }

        throw new ConflictoDeEstado(
            "La referencia {$referenciaPasarela} no pertenece a esta conciliacion",
        );
    }

    /**
     * Se cierra cuando ya no queda nada por explicar: o todo coincide, o cada
     * item con diferencia tiene su observacion.
     */
    public function cerrar(): void
    {
        foreach ($this->items as $item) {
            if ($item->resultado() !== ResultadoConciliacionItem::COINCIDE && ! $item->estaRevisado()) {
                throw new ConflictoDeEstado(
                    "La referencia {$item->referenciaPasarela()} tiene diferencia y no esta revisada",
                );
            }
        }

        $this->estado = EstadoConciliacion::CONCILIADA;
    }
}
