<?php

declare(strict_types=1);

namespace Src\Pagos\Application\UseCase\CobrarPago;

use DomainException;
use Src\Pagos\Application\Port\PasarelaPago;
use Src\Pagos\Domain\EstadoTransaccion;
use Src\Pagos\Domain\Pago;
use Src\Pagos\Domain\PagoRepository;
use Src\Pagos\Domain\TipoTransaccion;
use Src\Pagos\Domain\TransaccionPago;
use Src\Pagos\Domain\TransaccionPagoRepository;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Application\Port\UuidGenerator;

/**
 * CU-PAG-03 CobrarPago (NEG, REST / INT).
 *
 * Es el paso de la saga donde MS-02 pide el cobro, y el caso de uso que el
 * documento de arquitectura usa como ejemplo. El orden importa:
 *
 *  1. la llamada a la pasarela va FUERA de la transaccion de base de datos,
 *     porque una llamada HTTP lenta no debe tener abierta una transaccion;
 *  2. el cambio de estado, la transaccion registrada y el evento van DENTRO de
 *     una sola transaccion (patron Outbox), asi nunca se publica
 *     `pago.capturado` de un pago que no quedo guardado.
 *
 * Si la pasarela rechaza, el pago queda FALLIDO y se emite `pago.fallido` para
 * que MS-02 compense y libere la disponibilidad.
 */
final class CobrarPagoHandler
{
    public function __construct(
        private readonly PagoRepository $pagos,
        private readonly TransaccionPagoRepository $transacciones,
        private readonly PasarelaPago $pasarela,
        private readonly EventPublisher $eventos,
        private readonly TransactionManager $tx,
        private readonly UuidGenerator $uuid,
    ) {
    }

    public function __invoke(CobrarPagoCommand $comando): Pago
    {
        $pago = $this->pagos->buscar($comando->pagoId)
            ?? throw new DomainException("El pago {$comando->pagoId} no existe");

        // Un pago ya capturado no se vuelve a cobrar: el reintento es idempotente.
        if ($pago->estaCapturado()) {
            return $pago;
        }

        // 1) Llamada externa FUERA de la transaccion de base de datos.
        $resultado = $this->pasarela->cobrar(
            $pago->pagoId(),
            $pago->total(),
            $comando->idempotencyKey ?? $pago->idempotencyKey(),
            $pago->metodo(),
        );

        // 2) Estado + transaccion + evento en UNA sola transaccion.
        return $this->tx->ejecutar(function () use ($pago, $resultado): Pago {
            if ($resultado->exitoso) {
                $pago->capturar((string) $resultado->referencia, $this->pasarela->nombre());
            } else {
                $pago->marcarFallido($resultado->motivo);
            }

            $this->pagos->guardar($pago);

            $this->transacciones->agregar(TransaccionPago::registrar(
                transaccionId: $this->uuid->generar(),
                pagoId: $pago->pagoId(),
                tipo: TipoTransaccion::CAPTURA,
                monto: $pago->total(),
                estado: $resultado->exitoso ? EstadoTransaccion::EXITOSA : EstadoTransaccion::FALLIDA,
                referenciaPasarela: $resultado->referencia,
                respuestaPasarela: $resultado->respuesta,
            ));

            $this->eventos->publicar($pago->sacarEventos());

            return $pago;
        });
    }
}
