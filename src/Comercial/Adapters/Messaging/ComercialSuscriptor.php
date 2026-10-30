<?php

declare(strict_types=1);

namespace Src\Comercial\Adapters\Messaging;

use Src\Comercial\Application\UseCase\AplicarPromocion\AplicarPromocionUseCase;
use Src\Comercial\Application\UseCase\AplicarPromocion\RegistrarUsoDePromocionRequest;
use Src\Shared\Adapters\Messaging\SuscriptorDeEventos;
use Src\Shared\Domain\Money;

/** un pago capturado con `promocion_id` cuenta un uso de esa promocion. */
final class ComercialSuscriptor implements SuscriptorDeEventos
{
    public function __construct(private readonly AplicarPromocionUseCase $registrarUso) {}

    public function manejadores(): array
    {
        return [
            'pago.capturado' => function (array $d, array $mensaje): void {
                if (empty($d['promocion_id'])) {
                    return;
                }
                $moneda = (string) ($d['moneda'] ?? 'BOB');

                $this->registrarUso->registrarUso(new RegistrarUsoDePromocionRequest(
                    promocionId: (string) $d['promocion_id'],
                    descuentoCentavos: Money::deDecimal((string) ($d['descuento'] ?? '0'), $moneda)->centavos(),
                    moneda: $moneda,
                    reservaId: $d['reserva_id'] ?? null,
                    pagoId: $d['pago_id'] ?? null,
                    sagaId: $mensaje['saga_id'] ?? null,
                ));
            },
        ];
    }
}
