<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\RecibirNotificacionPasarela;

use Src\Pagos\Domain\EstadoTransaccion;
use Src\Pagos\Domain\PagoRepository;
use Src\Pagos\Domain\TipoTransaccion;
use Src\Pagos\Domain\TransaccionPago;
use Src\Pagos\Domain\TransaccionPagoRepository;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;

/**
 * CU-PAG-05 RecibirNotificacionPasarela (NEG, webhook REST).
 *
 * La pasarela avisa por su cuenta, puede avisar tarde y puede avisar dos veces.
 * El filtro de repeticion es la `referencia_pasarela`, que es UNIQUE en
 * `transaccion_pago`: si ya existe una transaccion con esa referencia, el
 * webhook se descarta sin tocar nada.
 *
 * Tambien cubre el caso en que el cobro se confirma por webhook y no por la
 * respuesta sincrona de CU-PAG-03.
 */
final class RecibirNotificacionPasarelaHandler
{
    public function __construct(
        private readonly PagoRepository $pagos,
        private readonly TransaccionPagoRepository $transacciones,
        private readonly EventPublisher $eventos,
        private readonly TransactionManager $tx,
        private readonly UuidGenerator $uuid,
    ) {}

    public function __invoke(RecibirNotificacionPasarelaCommand $comando): ResultadoNotificacion
    {
        if ($this->transacciones->buscarPorReferencia($comando->referenciaPasarela) !== null) {
            return ResultadoNotificacion::yaProcesada($comando->referenciaPasarela);
        }

        $pago = $this->pagos->buscar($comando->pagoId)
            ?? throw new RecursoNoEncontrado("El pago {$comando->pagoId} no existe");

        // Un pago ya cerrado no cambia de estado por un webhook atrasado, pero
        // la transaccion si se registra: el libro con la pasarela es append-only
        // y la conciliacion la necesita.
        $cambiaEstado = ! $pago->estaCapturado()
            && in_array($pago->estado()->value, ['PENDIENTE', 'AUTORIZADO'], true);

        return $this->tx->ejecutar(function () use ($comando, $pago, $cambiaEstado): ResultadoNotificacion {
            if ($cambiaEstado) {
                if ($comando->exitosa) {
                    $pago->capturar($comando->referenciaPasarela, $comando->pasarela);
                } else {
                    $pago->marcarFallido($comando->motivo);
                }

                $this->pagos->guardar($pago);
            }

            $this->transacciones->agregar(TransaccionPago::registrar(
                transaccionId: $this->uuid->generar(),
                pagoId: $pago->pagoId(),
                tipo: $comando->tipo ?? TipoTransaccion::CAPTURA,
                monto: $comando->montoNotificado ?? $pago->total(),
                estado: $comando->exitosa ? EstadoTransaccion::EXITOSA : EstadoTransaccion::FALLIDA,
                referenciaPasarela: $comando->referenciaPasarela,
                respuestaPasarela: $comando->payload,
            ));

            $this->eventos->publicar($pago->sacarEventos());

            return ResultadoNotificacion::aplicada($comando->referenciaPasarela, $cambiaEstado);
        });
    }
}
