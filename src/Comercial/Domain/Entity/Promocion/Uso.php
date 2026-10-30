<?php

declare(strict_types=1);

namespace Src\Comercial\Domain\Entity\Promocion;

use DateTimeImmutable;
use Src\Comercial\Domain\Enum\EstadoPromocion;
use Src\Comercial\Domain\Event\PromocionAplicada;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Money;

/**
 * Registro del uso de la promocion en un pago capturado.
 *
 * Parte de `Promocion`: usa sus campos privados.
 */
trait Uso
{
    /**
     * La promocion se uso en un pago capturado.
     *
     * Falla antes de pasarse del tope (`chk_promocion_usos` en la base): el
     * caso de uso corre dentro de una transaccion, asi que nada se guarda.
     */
    public function registrarUso(
        DateTimeImmutable $aplicadaEn,
        Money $descuento,
        ?string $reservaId = null,
        ?string $pagoId = null,
        ?string $sagaId = null,
    ): void {
        if ($this->estado === EstadoPromocion::CANCELADA) {
            throw new ConflictoDeEstado("La promocion {$this->promocionId} esta CANCELADA, no se puede usar");
        }

        if (! $this->tope->hayDisponibles()) {
            throw new ConflictoDeEstado(
                "La promocion {$this->promocionId} alcanzo su tope de usos ({$this->tope->maximo()})",
            );
        }

        $this->tope = $this->tope->registrarUno();

        $this->registrar(new PromocionAplicada(
            promocionId: $this->promocionId,
            codigo: $this->codigo?->valor(),
            anfitrionId: $this->alcance->anfitrionId(),
            tipoDescuento: $this->valor->tipo(),
            descuento: $descuento,
            financiadaPor: $this->financiadaPor,
            usosActuales: $this->tope->actuales(),
            maxUsos: $this->tope->maximo(),
            aplicadaEn: $aplicadaEn,
            reservaId: $reservaId,
            pagoId: $pagoId,
            sagaId: $sagaId,
        ));
    }
}
