<?php

declare(strict_types=1);

namespace Src\Promociones\Presentation\Messaging;

use Src\Promociones\Application\UseCase\RegistrarUsoDePromocion\RegistrarUsoDePromocionCommand;
use Src\Promociones\Application\UseCase\RegistrarUsoDePromocion\RegistrarUsoDePromocionHandler;
use Src\Shared\Domain\Money;
use Src\Shared\Presentation\Messaging\SuscriptorDeEventos;

/** CU-PRO-03: un pago capturado con `promocion_id` cuenta un uso de esa promocion. */
final class PromocionesSuscriptor implements SuscriptorDeEventos
{
    public function __construct(private readonly RegistrarUsoDePromocionHandler $registrarUso) {}

    public function manejadores(): array
    {
        return [
            'pago.capturado' => function (array $d, array $mensaje): void {
                if (empty($d['promocion_id'])) {
                    return;
                }
                $moneda = (string) ($d['moneda'] ?? 'BOB');

                ($this->registrarUso)(new RegistrarUsoDePromocionCommand(
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
