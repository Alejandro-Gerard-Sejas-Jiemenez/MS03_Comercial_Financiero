<?php

declare(strict_types=1);

namespace Src\Comercial\Domain\Entity\Promocion;

use DateTimeImmutable;
use Src\Comercial\Domain\Enum\EstadoPromocion;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Domain\Money;

/**
 * Calculo del descuento y respuesta a "¿le sirve a esta reserva?".
 *
 * Parte de `Promocion`: usa sus campos privados.
 */
trait Aplicabilidad
{
    /**
     * Descuento sobre el subtotal de la reserva. El subtotal no puede ser
     * negativo y debe estar en la moneda de la promocion.
     */
    public function calcularDescuento(Money $subtotal): Money
    {
        if ($subtotal->esNegativo()) {
            throw new ReglaDeNegocioViolada('El subtotal no puede ser negativo');
        }

        if ($subtotal->moneda() !== $this->moneda) {
            throw new ReglaDeNegocioViolada(
                "La promocion {$this->promocionId} esta en {$this->moneda} y el subtotal en {$subtotal->moneda()}",
            );
        }

        return $this->valor->sobre($subtotal);
    }

    /**
     * Si la promocion alcanza a esa reserva: ACTIVA, vigente en la fecha, con
     * usos disponibles, noches suficientes y dentro del alcance de anfitrion y
     * alojamiento.
     */
    public function aplicaA(
        int $noches,
        DateTimeImmutable $fecha,
        ?string $alojamientoId = null,
        ?string $anfitrionId = null,
    ): bool {
        return $this->motivoDeNoAplicar($noches, $fecha, $alojamientoId, $anfitrionId) === null;
    }

    /** Igual que `aplicaA()`, pero falla con el motivo exacto. */
    public function asegurarAplicableA(
        int $noches,
        DateTimeImmutable $fecha,
        ?string $alojamientoId = null,
        ?string $anfitrionId = null,
    ): void {
        $motivo = $this->motivoDeNoAplicar($noches, $fecha, $alojamientoId, $anfitrionId);

        if ($motivo !== null) {
            throw new ReglaDeNegocioViolada($motivo);
        }
    }

    private function motivoDeNoAplicar(
        int $noches,
        DateTimeImmutable $fecha,
        ?string $alojamientoId,
        ?string $anfitrionId,
    ): ?string {
        $id = $this->promocionId;

        if ($this->estado !== EstadoPromocion::ACTIVA) {
            return "La promocion {$id} esta {$this->estado->value}, no se puede aplicar";
        }

        if (! $this->vigencia->incluye($fecha)) {
            return "La promocion {$id} no esta vigente el ".$fecha->format('Y-m-d');
        }

        if (! $this->tope->hayDisponibles()) {
            return "La promocion {$id} alcanzo su tope de usos ({$this->tope->maximo()})";
        }

        if ($noches < $this->minNoches) {
            return "La promocion {$id} exige un minimo de {$this->minNoches} noches";
        }

        if (! $this->alcance->alcanzaAnfitrion($anfitrionId)) {
            return "La promocion {$id} no aplica a los alojamientos de ese anfitrion";
        }

        if (! $this->alcance->alcanzaAlojamiento($alojamientoId)) {
            return "La promocion {$id} no aplica a ese alojamiento";
        }

        return null;
    }
}
