<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Presentation\Messaging;

use Src\Liquidaciones\Application\UseCase\MarcarComisionesLiquidables\MarcarComisionesLiquidablesCommand;
use Src\Liquidaciones\Application\UseCase\MarcarComisionesLiquidables\MarcarComisionesLiquidablesHandler;
use Src\Liquidaciones\Application\UseCase\RegistrarComision\RegistrarComisionCommand;
use Src\Liquidaciones\Application\UseCase\RegistrarComision\RegistrarComisionHandler;
use Src\Liquidaciones\Application\UseCase\RetenerLiquidacionesDeAnfitrion\RetenerLiquidacionesDeAnfitrionCommand;
use Src\Liquidaciones\Application\UseCase\RetenerLiquidacionesDeAnfitrion\RetenerLiquidacionesDeAnfitrionHandler;
use Src\Liquidaciones\Application\UseCase\RevertirComision\RevertirComisionCommand;
use Src\Liquidaciones\Application\UseCase\RevertirComision\RevertirComisionHandler;
use Src\Monetizacion\Domain\FinanciamientoDescuento;
use Src\Promociones\Domain\PromocionRepository;
use Src\Referencias\Domain\ReservaReferenceRepository;
use Src\Shared\Domain\Money;
use Src\Shared\Presentation\Messaging\SuscriptorDeEventos;

/**
 * CU-LIQ-01, 02, 03 y 08.
 *
 * `pago.capturado` no trae `financiada_por`: se resuelve con la promocion del
 * pago (pendiente #2 de CASOS_DE_USO.md). La categoria del alojamiento sale de
 * la copia local de la reserva.
 */
final class LiquidacionesSuscriptor implements SuscriptorDeEventos
{
    public function __construct(
        private readonly RegistrarComisionHandler $registrarComision,
        private readonly RevertirComisionHandler $revertirComision,
        private readonly MarcarComisionesLiquidablesHandler $marcarLiquidables,
        private readonly RetenerLiquidacionesDeAnfitrionHandler $retener,
        private readonly PromocionRepository $promociones,
        private readonly ReservaReferenceRepository $reservas,
    ) {}

    public function manejadores(): array
    {
        return [
            'pago.capturado' => function (array $d): void {
                $moneda = (string) ($d['moneda'] ?? 'BOB');
                $centavos = static fn (string $campo): int => Money::deDecimal((string) ($d[$campo] ?? '0'), $moneda)->centavos();

                $promocion = empty($d['promocion_id']) ? null : $this->promociones->buscar((string) $d['promocion_id']);
                $reserva = $this->reservas->buscar((string) $d['reserva_id']);

                ($this->registrarComision)(new RegistrarComisionCommand(
                    pagoId: (string) $d['pago_id'],
                    reservaId: (string) $d['reserva_id'],
                    anfitrionId: (string) $d['anfitrion_id'],
                    subtotalCentavos: $centavos('subtotal_alojamiento'),
                    totalCentavos: $centavos('monto_total'),
                    descuentoCentavos: $centavos('descuento'),
                    moneda: $moneda,
                    financiamiento: $promocion?->financiadaPor() ?? FinanciamientoDescuento::ANFITRION,
                    categoriaAlojamiento: $reserva?->categoriaAlojamiento,
                ));
            },
            'reembolso.procesado' => function (array $d): void {
                ($this->revertirComision)(new RevertirComisionCommand(
                    pagoId: (string) $d['pago_id'],
                    esTotal: (bool) ($d['es_total'] ?? true),
                ));
            },
            'estadia.completada' => function (array $d): void {
                ($this->marcarLiquidables)(new MarcarComisionesLiquidablesCommand((string) $d['reserva_id']));
            },
            'anfitrion.suspendido' => function (array $d): void {
                ($this->retener)(new RetenerLiquidacionesDeAnfitrionCommand((string) $d['anfitrion_id']));
            },
        ];
    }
}
