<?php

declare(strict_types=1);

namespace Src\Financiero\Application\UseCase\GestionarReembolsos;

use Src\Financiero\Application\Port\PagoRepository;
use Src\Financiero\Application\Port\PasarelaPago;
use Src\Financiero\Application\Port\ReembolsoRepository;
use Src\Financiero\Application\Port\ResultadoDevolucion;
use Src\Financiero\Application\Port\TransaccionPagoRepository;
use Src\Financiero\Application\Response\ReembolsoResponse;
use Src\Financiero\Domain\Entity\Pago;
use Src\Financiero\Domain\Entity\Reembolso;
use Src\Financiero\Domain\Entity\TransaccionPago;
use Src\Financiero\Domain\Enum\EstadoTransaccion;
use Src\Financiero\Domain\Enum\TipoTransaccion;
use Src\Financiero\Domain\Policy\PoliticaDeReembolso;
use Src\Shared\Application\Port\AuditLogger;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\ReservaReferenceRepository;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;

/** Caso de uso GestionarReembolsos: implementa la interfaz de entrada GestionarReembolsosUseCase. */
final class GestionarReembolsosService implements GestionarReembolsosUseCase
{
    public function __construct(
        private readonly PagoRepository $pagos,
        private readonly ReembolsoRepository $reembolsos,
        private readonly ReservaReferenceRepository $reservas,
        private readonly PoliticaDeReembolso $politica,
        private readonly UuidGenerator $uuid,
        private readonly Clock $clock,
        private readonly TransactionManager $tx,
        private readonly AuditLogger $auditoria,
        private readonly TransaccionPagoRepository $transacciones,
        private readonly PasarelaPago $pasarela,
        private readonly EventPublisher $eventos,
    ) {}

    /**
     * Disparador: EVT `reserva.cancelada`.
     *
     * Lo dispara MS-02 al cancelar una reserva. Crea el reembolso en SOLICITADO con
     * el monto que manda la politica de cancelacion; no mueve dinero todavia.
     *
     * Si la politica dice que no corresponde devolver nada, no se crea ningun
     * reembolso: queda registrado en el resultado y nadie tiene que aprobar un
     * reembolso de cero.
     *
     * Las fechas vienen de la copia local `ref_reserva`, no de una llamada a MS-02:
     * asi esto funciona aunque MS-02 este caido.
     */
    public function solicitar(SolicitarReembolsoRequest $comando): ?ReembolsoResponse
    {
        $pago = $this->pagos->buscarVivoPorReserva($comando->reservaId)
            ?? throw new RecursoNoEncontrado(
                "La reserva {$comando->reservaId} no tiene un pago vivo que reembolsar",
            );

        $pago->asegurarAdmiteReembolso();

        $reserva = $this->reservas->buscar($comando->reservaId)
            ?? throw new RecursoNoEncontrado(
                "No hay copia local de la reserva {$comando->reservaId}; falta el evento de MS-02",
            );

        // La politica decide cuanto devolver y nunca pasa de lo que queda por devolver.
        $monto = $this->politica->montoSolicitable(
            $pago,
            $this->pagos->totalReembolsado($pago->pagoId()),
            $comando->montoForzado,
            $comando->fechaCancelacion ?? $this->clock->hoy(),
            $reserva->fechaCheckin,
            $comando->motivo,
        );

        if ($monto === null) {
            return null;
        }
        $reembolso = Reembolso::solicitar(
            reembolsoId: $this->uuid->generar(),
            pagoId: $pago->pagoId(),
            monto: $monto,
            motivo: $comando->motivo,
            detalle: $comando->detalle,
            solicitadoPor: $comando->solicitadoPor,
        );

        return ReembolsoResponse::desdeOpcional($this->tx->ejecutar(function () use ($reembolso): Reembolso {
            $this->reembolsos->guardar($reembolso);

            return $reembolso;
        }));
    }

    /**
     * Disparador: REST.
     *
     * Decision humana del Administrador: APROBADO o RECHAZADO. Es un caso de uso
     * aparte de porque lo dispara una persona distinta y
     * queda auditado con su nombre.
     */
    public function revisar(AprobarReembolsoRequest $comando): ReembolsoResponse
    {
        $reembolso = $this->reembolsos->buscar($comando->reembolsoId)
            ?? throw new RecursoNoEncontrado("El reembolso {$comando->reembolsoId} no existe");

        $estadoAnterior = $reembolso->estado()->value;

        return ReembolsoResponse::desde($this->tx->ejecutar(function () use ($reembolso, $comando, $estadoAnterior): Reembolso {
            if ($comando->aprobado) {
                $reembolso->aprobar($comando->detalle);
            } else {
                $reembolso->rechazar(
                    $comando->detalle ?? throw new ReglaDeNegocioViolada('Un rechazo necesita explicacion'),
                );
            }

            $this->reembolsos->guardar($reembolso);

            $this->auditoria->registrar(
                $comando->aprobado ? 'APROBAR_REEMBOLSO' : 'RECHAZAR_REEMBOLSO',
                'reembolso',
                $reembolso->reembolsoId(),
                ['estado' => $estadoAnterior],
                ['estado' => $reembolso->estado()->value, 'detalle' => $reembolso->detalle()],
                $comando->revisadoPor,
            );

            return $reembolso;
        }));
    }

    /**
     * Disparador: INT / JOB.
     *
     * Devuelve el dinero en la pasarela y emite `reembolso.procesado`, que MS-02
     * usa para marcar la reserva y Financiero para revertir la comision
     *.
     *
     * Mismo orden que la llamada a la pasarela va fuera de la
     * transaccion, y el cambio de estado del reembolso, el del pago, la transaccion
     * registrada y el evento van dentro de una sola.
     *
     * Si la pasarela falla, el reembolso queda APROBADO (no PROCESADO) y la
     * transaccion FALLIDA queda registrada: el JOB lo reintenta y nunca se emite el
     * evento de un reembolso que no ocurrio.
     */
    public function procesar(ProcesarReembolsoRequest $comando): ReembolsoResponse
    {
        $reembolso = $this->reembolsos->buscar($comando->reembolsoId)
            ?? throw new RecursoNoEncontrado("El reembolso {$comando->reembolsoId} no existe");

        if ($reembolso->estaProcesado()) {
            return ReembolsoResponse::desde($reembolso);
        }

        $pago = $this->pagos->buscar($reembolso->pagoId())
            ?? throw new RecursoNoEncontrado("El pago {$reembolso->pagoId()} no existe");

        $acumulado = $this->pagos->totalReembolsado($pago->pagoId())->mas($reembolso->monto());

        if ($acumulado->esMayorQue($pago->total())) {
            throw new ReglaDeNegocioViolada(
                "El reembolso dejaria el acumulado en {$acumulado->aDecimal()}, mayor que el total "
                ."cobrado {$pago->total()->aDecimal()}",
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

            throw new ReglaDeNegocioViolada(
                "La pasarela rechazo el reembolso {$reembolso->reembolsoId()}: {$resultado->motivo}",
            );
        }

        // 3) Todo lo demas, DENTRO de una sola transaccion.
        return ReembolsoResponse::desde($this->tx->ejecutar(
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
        ));
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
