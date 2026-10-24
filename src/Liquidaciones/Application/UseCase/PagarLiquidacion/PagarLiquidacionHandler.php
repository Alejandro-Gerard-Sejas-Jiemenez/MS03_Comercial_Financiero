<?php

declare(strict_types=1);

namespace Src\Liquidaciones\Application\UseCase\PagarLiquidacion;

use DomainException;
use Src\Liquidaciones\Domain\Asiento;
use Src\Liquidaciones\Domain\LibroContableRepository;
use Src\Liquidaciones\Domain\Liquidacion;
use Src\Liquidaciones\Domain\LiquidacionRepository;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;

/**
 * CU-LIQ-07 PagarLiquidacion (NEG, REST).
 *
 * Registra la transferencia al anfitrion, deja la liquidacion PAGADA, escribe
 * el asiento que cancela la deuda y emite `liquidacion.pagada`.
 *
 * Todo en una sola transaccion, con el evento por Outbox: no puede quedar una
 * liquidacion PAGADA sin asiento, ni un evento publicado de una transferencia
 * que no se guardo.
 */
final class PagarLiquidacionHandler
{
    public function __construct(
        private readonly LiquidacionRepository $liquidaciones,
        private readonly LibroContableRepository $libro,
        private readonly EventPublisher $eventos,
        private readonly AuditLogger $auditoria,
        private readonly UuidGenerator $uuid,
        private readonly TransactionManager $tx,
    ) {
    }

    public function __invoke(PagarLiquidacionCommand $comando): Liquidacion
    {
        $liquidacion = $this->liquidaciones->buscar($comando->liquidacionId)
            ?? throw new DomainException("La liquidacion {$comando->liquidacionId} no existe");

        if ($liquidacion->estaPagada()) {
            return $liquidacion;
        }

        $asiento = Asiento::porLiquidacionPagada(
            asientoId: $this->uuid->generar(),
            liquidacionId: $liquidacion->liquidacionId(),
            anfitrionId: $liquidacion->anfitrionId(),
            totalNeto: $liquidacion->totalNeto(),
        );

        return $this->tx->ejecutar(function () use ($liquidacion, $asiento, $comando): Liquidacion {
            $liquidacion->pagar($comando->referenciaTransferencia);

            $this->liquidaciones->guardar($liquidacion);
            $this->libro->registrar($asiento);
            $this->eventos->publicar($liquidacion->sacarEventos());

            $this->auditoria->registrar(
                'PAGAR_LIQUIDACION',
                'liquidacion',
                $liquidacion->liquidacionId(),
                ['estado' => 'APROBADA'],
                [
                    'estado' => $liquidacion->estado()->value,
                    'total_neto' => $liquidacion->totalNeto()->aDecimal(),
                    'referencia_transferencia' => $comando->referenciaTransferencia,
                ],
                $comando->pagadoPor,
            );

            return $liquidacion;
        });
    }
}
