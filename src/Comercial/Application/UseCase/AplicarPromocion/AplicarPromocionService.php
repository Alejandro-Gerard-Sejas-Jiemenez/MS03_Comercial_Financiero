<?php

declare(strict_types=1);

namespace Src\Comercial\Application\UseCase\AplicarPromocion;

use DateTimeImmutable;
use Src\Comercial\Application\Port\CalculadorDeDescuento;
use Src\Comercial\Application\Port\DescuentoAplicado;
use Src\Comercial\Application\Port\PromocionRepository;
use Src\Comercial\Application\Port\SolicitudDescuento;
use Src\Comercial\Application\Response\PromocionResponse;
use Src\Comercial\Domain\Entity\Promocion;
use Src\Comercial\Domain\Policy\SelectorDePromocion;
use Src\Comercial\Domain\ValueObject\CodigoCupon;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Application\Port\EventPublisher;
use Src\Shared\Application\Port\TransactionManager;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Money;

/** Caso de uso AplicarPromocion: implementa la interfaz de entrada AplicarPromocionUseCase. */
final class AplicarPromocionService implements AplicarPromocionUseCase, CalculadorDeDescuento
{
    public function __construct(
        private readonly PromocionRepository $promociones,
        private readonly Clock $clock,
        private readonly EventPublisher $eventos,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
     * Disparador: REST / INT.
     *
     * Este mismo servicio implementa el puerto `CalculadorDeDescuento` que
     * consume Financiero: no hace falta un adaptador, porque la firma del puerto es la
     * misma operacion del caso de uso.
     *
     * Tres caminos:
     *  - con `codigoCupon`: el cliente escribio un cupon, asi que si no sirve se
     *    explica el motivo con una excepcion de dominio;
     *  - con `promocionId`: igual que el cupon, la promocion se pidio a proposito;
     *  - sin ninguno: se buscan las promociones automaticas (temporada, ultimo
     *    minuto, estadia prolongada) y se aplica la mas conveniente para el
     *    cliente; si ninguna aplica, devuelve null sin fallar.
     */
    public function calcularDescuento(SolicitudDescuento $solicitud): ?DescuentoAplicado
    {
        $fecha = $solicitud->fecha ?? $this->clock->ahora();
        $subtotal = Money::deCentavos($solicitud->subtotalCentavos, $solicitud->moneda);

        $pedida = $this->promocionPedida($solicitud);

        if ($pedida !== null) {
            $pedida->asegurarAplicableA(
                $solicitud->noches,
                $fecha,
                $solicitud->alojamientoId,
                $solicitud->anfitrionId,
            );

            return $this->descuentoDe($pedida, $subtotal);
        }

        if ($solicitud->codigoCupon !== null || $solicitud->promocionId !== null) {
            return null;
        }

        return $this->mejorAutomatica($solicitud, $fecha, $subtotal);
    }

    public function descuentoAplicable(SolicitudDescuento $solicitud): ?DescuentoAplicado
    {
        return $this->calcularDescuento($solicitud);
    }

    /**
     * La promocion que el cliente pidio explicitamente, por cupon o por id.
     * Un codigo que nadie emitio es un error del cliente, no un descuento de
     * cero: si se ignorara en silencio, la cotizacion mostraria un total que el
     * cliente no espera.
     */
    private function promocionPedida(SolicitudDescuento $solicitud): ?Promocion
    {
        if ($solicitud->codigoCupon !== null) {
            $codigo = CodigoCupon::desde($solicitud->codigoCupon)->valor();

            return $this->promociones->buscarPorCodigo($codigo)
                ?? throw new RecursoNoEncontrado("El cupon {$codigo} no existe");
        }

        if ($solicitud->promocionId !== null) {
            return $this->promociones->buscar($solicitud->promocionId)
                ?? throw new RecursoNoEncontrado("La promocion {$solicitud->promocionId} no existe");
        }

        return null;
    }

    /** Entre las promociones automaticas aplicables gana la de mayor descuento (ver `SelectorDePromocion`). */
    private function mejorAutomatica(
        SolicitudDescuento $solicitud,
        DateTimeImmutable $fecha,
        Money $subtotal,
    ): ?DescuentoAplicado {
        $mejor = (new SelectorDePromocion)->mejorEntre(
            $this->promociones->vigentesSinCupon($fecha, $solicitud->alojamientoId, $solicitud->anfitrionId),
            $solicitud->noches,
            $fecha,
            $solicitud->alojamientoId,
            $solicitud->anfitrionId,
            $subtotal,
        );

        return $mejor === null ? null : $this->descuentoDe($mejor, $subtotal);
    }

    private function descuentoDe(Promocion $promocion, Money $subtotal): DescuentoAplicado
    {
        $monto = $promocion->calcularDescuento($subtotal);

        return new DescuentoAplicado(
            promocionId: $promocion->promocionId(),
            descuentoCentavos: $monto->centavos(),
            moneda: $monto->moneda(),
            financiamiento: $promocion->financiadaPor(),
        );
    }

    /**
     * Disparador: INT: `PagoCapturado`.
     *
     * Incrementa `usos_actuales` y emite `promocion.aplicada`, que consume MS-04
     * para el historico de F11. El incremento y la publicacion van en la misma
     * transaccion (patron Outbox): si el evento no se puede guardar, el uso no
     * queda contado.
     *
     * Si la promocion ya alcanzo su tope, la entidad falla con una excepcion de
     * dominio y la transaccion se revierte, en vez de pasarse del `max_usos`.
     */
    public function registrarUso(RegistrarUsoDePromocionRequest $comando): PromocionResponse
    {
        return PromocionResponse::desde($this->transacciones->ejecutar(function () use ($comando): Promocion {
            $promocion = $this->promociones->buscar($comando->promocionId)
                ?? throw new RecursoNoEncontrado("La promocion {$comando->promocionId} no existe");

            $promocion->registrarUso(
                $comando->aplicadaEn ?? $this->clock->ahora(),
                Money::deCentavos($comando->descuentoCentavos, $comando->moneda),
                $comando->reservaId,
                $comando->pagoId,
                $comando->sagaId,
            );

            $this->promociones->guardar($promocion);
            $this->eventos->publicar($promocion->sacarEventos());

            return $promocion;
        }));
    }
}
