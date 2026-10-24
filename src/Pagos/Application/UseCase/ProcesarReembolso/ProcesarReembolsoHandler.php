<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\ProcesarReembolso;

use DomainException;
use Src\Pagos\Application\Port\PasarelaPago;
use Src\Pagos\Application\Port\ResultadoDevolucion;
use Src\Pagos\Domain\EstadoTransaccion;
use Src\Pagos\Domain\Pago;
use Src\Pagos\Domain\PagoRepository;
use Src\Pagos\Domain\Reembolso;
use Src\Pagos\Domain\ReembolsoRepository;
use Src\Pagos\Domain\TipoTransaccion;
use Src\Pagos\Domain\TransaccionPago;
use Src\Pagos\Domain\TransaccionPagoRepository;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;

/**
 * CU-PAG-09 ProcesarReembolso (NEG, INT / JOB).
 *
 * Devuelve el dinero en la pasarela y emite `reembolso.procesado`, que MS-02
 * usa para marcar la reserva y Liquidaciones para revertir la comision
 * (CU-LIQ-02).
 *
 * Mismo orden que CU-PAG-03: la llamada a la pasarela va fuera de la
 * transaccion, y el cambio de estado del reembolso, el del pago, la transaccion
 * registrada y el evento van dentro de una sola.
 *
 * Si la pasarela falla, el reembolso queda APROBADO (no PROCESADO) y la
 * transaccion FALLIDA queda registrada: el JOB lo reintenta y nunca se emite el
 * evento de un reembolso que no ocurrio.
 */
final class ProcesarReembolsoHandler
{
    public function __construct(
        private readonly ReembolsoRepository $reembolsos,
        private readonly PagoRepository $pagos,
        private readonly TransaccionPagoRepository $transacciones,
        private readonly PasarelaPago $pasarela,
        private readonly EventPublisher $eventos,
        private readonly TransactionManager $tx,
        private readonly UuidGenerator $uuid,
    ) {
    }

    public function __invoke(ProcesarReembolsoCommand $comando): Reembolso
    {
        $reembolso = $this->reembolsos->buscar($comando->reembolsoId)
            ?? throw new DomainException("El reembolso {$comando->reembolsoId} no existe");

        if ($reembolso->estaProcesado()) {
            return $reembolso;
        }

        $pago = $this->pagos->buscar($reembolso->pagoId())
            ?? throw new DomainException("El pago {$reembolso->pagoId()} no existe");

        $acumulado = $this->pagos->totalReembolsado($pago->pagoId())->mas($reembolso->monto());

        if ($acumulado->esMayorQue($pago->total())) {
            throw new DomainException(
                "El reembolso dejaria el acumulado en {$acumulado->aDecimal()}, mayor que el total "
                . "cobrado {$pago->total()->aDecimal()}",
            );
        }

        // 1) Devolucion en la pasarela, FUERA de la transaccion.
        $resultado = $this->pasarela->devolver(
            $pago->pagoId(),
            $reembolso->monto(),
            (string) $pago->referenciaPasarela(),
            $comando->idempotencyKey ?? "reembolso-{$reembolso->reembolsoId()}",
        );

        // 2) Si la pasarela rechazo, el intento fallido se guarda en su propia
        //    transaccion: si fuera dentro de la del exito, el rollback lo
        //    borraria y se perderia el rastro del intento.
        if (! $resultado->exitoso) {
            $this->tx->ejecutar(fn (): null => $this->registrarIntentoFallido($reembolso, $pago, $resultado));

            throw new DomainException(
                "La pasarela rechazo el reembolso {$reembolso->reembolsoId()}: {$resultado->motivo}",
            );
        }

        // 3) Todo lo demas, DENTRO de una sola transaccion.
        return $this->tx->ejecutar(
            function () use ($reembolso, $pago, $resultado, $acumulado): Reembolso {
                $transaccionId = $this->uuid->generar();

                $this->transacciones->agregar(TransaccionPago::registrar(
                    transaccionId: $transaccionId,
                    pagoId: $pago->pagoId(),
                    tipo: TipoTransaccion::REEMBOLSO,
                    monto: $reembolso->monto(),
                    estado: EstadoTransaccion::EXITOSA,
                    referenciaPasarela: $resultado->referencia,
                    respuestaPasarela: $resultado->respuesta,
                ));

                $reembolso->procesar(
                    referenciaPasarela: (string) $resultado->referencia,
                    transaccionId: $transaccionId,
                    reservaId: $pago->reservaId(),
                    anfitrionId: $pago->anfitrionId(),
                    esTotal: $acumulado->igualA($pago->total()),
                    sagaId: $pago->sagaId(),
                );

                $pago->aplicarReembolso($acumulado);

                $this->reembolsos->guardar($reembolso);
                $this->pagos->guardar($pago);
                $this->eventos->publicar($reembolso->sacarEventos());

                return $reembolso;
            },
        );
    }

    private function registrarIntentoFallido(
        Reembolso $reembolso,
        Pago $pago,
        ResultadoDevolucion $resultado,
    ): null {
        $this->transacciones->agregar(TransaccionPago::registrar(
            transaccionId: $this->uuid->generar(),
            pagoId: $pago->pagoId(),
            tipo: TipoTransaccion::REEMBOLSO,
            monto: $reembolso->monto(),
            estado: EstadoTransaccion::FALLIDA,
            referenciaPasarela: null,
            respuestaPasarela: $resultado->respuesta,
        ));

        return null;
    }
}
