<?php

declare(strict_types=1);

namespace Src\Financiero\Adapters\Messaging;

use DateTimeImmutable;
use Src\Comercial\Application\Port\PromocionRepository;
use Src\Financiero\Application\UseCase\GestionarComisiones\GestionarComisionesUseCase;
use Src\Financiero\Application\UseCase\GestionarComisiones\RegistrarComisionRequest;
use Src\Financiero\Application\UseCase\GestionarComisiones\RevertirComisionRequest;
use Src\Financiero\Application\UseCase\GestionarLiquidaciones\GestionarLiquidacionesUseCase;
use Src\Financiero\Application\UseCase\GestionarLiquidaciones\MarcarComisionesLiquidablesRequest;
use Src\Financiero\Application\UseCase\GestionarLiquidaciones\RetenerLiquidacionesDeAnfitrionRequest;
use Src\Financiero\Application\UseCase\GestionarReembolsos\GestionarReembolsosUseCase;
use Src\Financiero\Application\UseCase\GestionarReembolsos\SolicitarReembolsoRequest;
use Src\Financiero\Domain\Enum\MotivoReembolso;
use Src\Shared\Adapters\Messaging\SuscriptorDeEventos;
use Src\Shared\Application\Port\ReservaReferenceRepository;
use Src\Shared\Domain\Money;

/**
 * Suscriptor del modulo Financiero: traduce los eventos de otros microservicios
 * a casos de uso de pagos, comisiones y liquidaciones.
 *
 * - `reserva.cancelada`: abre la solicitud de reembolso (queda SOLICITADO; un ADMIN la
 *   aprueba). `data`: reserva_id, cancelada_por (`ANFITRION` | `CLIENTE`), fecha_cancelacion?.
 * - `pago.capturado`: registra la comision. No trae `financiada_por`: se resuelve con la
 *   promocion del pago; la categoria sale de la copia local de la reserva.
 * - `reembolso.procesado`: revierte la comision.
 * - `estadia.completada`: marca las comisiones como liquidables.
 * - `anfitrion.suspendido`: retiene las liquidaciones del anfitrion.
 */
final class FinancieroSuscriptor implements SuscriptorDeEventos
{
    public function __construct(
        private readonly GestionarReembolsosUseCase $gestionarReembolsos,
        private readonly GestionarComisionesUseCase $gestionarComisiones,
        private readonly GestionarLiquidacionesUseCase $gestionarLiquidaciones,
        private readonly PromocionRepository $promociones,
        private readonly ReservaReferenceRepository $reservas,
    ) {}

    public function manejadores(): array
    {
        return [
            'reserva.cancelada' => function (array $d): void {
                $this->gestionarReembolsos->solicitar(new SolicitarReembolsoRequest(
                    reservaId: (string) $d['reserva_id'],
                    motivo: ($d['cancelada_por'] ?? 'CLIENTE') === 'ANFITRION'
                        ? MotivoReembolso::CANCELACION_ANFITRION
                        : MotivoReembolso::CANCELACION_CLIENTE,
                    fechaCancelacion: isset($d['fecha_cancelacion']) ? new DateTimeImmutable((string) $d['fecha_cancelacion']) : null,
                    solicitadoPor: null,
                ));
            },
            'pago.capturado' => function (array $d): void {
                $moneda = (string) ($d['moneda'] ?? 'BOB');
                $centavos = static fn (string $campo): int => Money::deDecimal((string) ($d[$campo] ?? '0'), $moneda)->centavos();

                $promocion = empty($d['promocion_id']) ? null : $this->promociones->buscar((string) $d['promocion_id']);
                $reserva = $this->reservas->buscar((string) $d['reserva_id']);

                $this->gestionarComisiones->registrar(new RegistrarComisionRequest(
                    pagoId: (string) $d['pago_id'],
                    reservaId: (string) $d['reserva_id'],
                    anfitrionId: (string) $d['anfitrion_id'],
                    subtotalCentavos: $centavos('subtotal_alojamiento'),
                    totalCentavos: $centavos('monto_total'),
                    descuentoCentavos: $centavos('descuento'),
                    moneda: $moneda,
                    financiamiento: $promocion?->financiadaPor(),
                    categoriaAlojamiento: $reserva?->categoriaAlojamiento,
                ));
            },
            'reembolso.procesado' => function (array $d): void {
                $this->gestionarComisiones->revertir(new RevertirComisionRequest(
                    pagoId: (string) $d['pago_id'],
                    esTotal: (bool) ($d['es_total'] ?? true),
                ));
            },
            'estadia.completada' => function (array $d): void {
                $this->gestionarLiquidaciones->marcarLiquidables(new MarcarComisionesLiquidablesRequest((string) $d['reserva_id']));
            },
            'anfitrion.suspendido' => function (array $d): void {
                $this->gestionarLiquidaciones->retener(new RetenerLiquidacionesDeAnfitrionRequest((string) $d['anfitrion_id']));
            },
        ];
    }
}
