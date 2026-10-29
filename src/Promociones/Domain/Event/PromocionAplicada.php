<?php

declare(strict_types=1);

namespace Src\Promociones\Domain\Event;

use DateTimeImmutable;
use Src\Monetizacion\Domain\FinanciamientoDescuento;
use Src\Promociones\Domain\TipoDescuento;
use Src\Shared\Domain\DomainEvent;
use Src\Shared\Domain\Money;

/**
 * CU-PRO-03: la promocion se uso en un pago capturado. Lo consume MS-04 para
 * el historico de demanda y tarifas (F11).
 *
 * El payload es una estructura de datos simple: por la frontera no viaja la
 * entidad ni la fila de la base de datos.
 */
final class PromocionAplicada implements DomainEvent
{
    public function __construct(
        private readonly string $promocionId,
        private readonly ?string $codigo,
        private readonly ?string $anfitrionId,
        private readonly TipoDescuento $tipoDescuento,
        private readonly Money $descuento,
        private readonly FinanciamientoDescuento $financiadaPor,
        private readonly int $usosActuales,
        private readonly ?int $maxUsos,
        private readonly DateTimeImmutable $aplicadaEn,
        private readonly ?string $reservaId = null,
        private readonly ?string $pagoId = null,
        private readonly ?string $sagaId = null,
    ) {}

    public function tipoEvento(): string
    {
        return 'promocion.aplicada';
    }

    public function agregadoTipo(): string
    {
        return 'promocion';
    }

    public function agregadoId(): string
    {
        return $this->promocionId;
    }

    public function payload(): array
    {
        return [
            'promocion_id' => $this->promocionId,
            'codigo' => $this->codigo,
            'anfitrion_id' => $this->anfitrionId,
            'tipo_descuento' => $this->tipoDescuento->value,
            'descuento' => $this->descuento->aDecimal(),
            'moneda' => $this->descuento->moneda(),
            'financiada_por' => $this->financiadaPor->value,
            'usos_actuales' => $this->usosActuales,
            'max_usos' => $this->maxUsos,
            'reserva_id' => $this->reservaId,
            'pago_id' => $this->pagoId,
            'aplicada_en' => $this->aplicadaEn->format(DateTimeImmutable::ATOM),
        ];
    }

    public function sagaId(): ?string
    {
        return $this->sagaId;
    }
}
