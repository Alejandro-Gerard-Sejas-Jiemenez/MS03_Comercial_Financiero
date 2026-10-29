<?php

declare(strict_types=1);

namespace Src\Promociones\Application\UseCase\ValidarCuponYCalcularDescuento;

use DateTimeImmutable;
use Src\Promociones\Application\Port\CalculadorDeDescuento;
use Src\Promociones\Application\Port\DescuentoAplicado;
use Src\Promociones\Application\Port\SolicitudDescuento;
use Src\Promociones\Domain\Promocion;
use Src\Promociones\Domain\PromocionRepository;
use Src\Shared\Application\Port\Clock;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Money;

/**
 * CU-PRO-02 ValidarCuponYCalcularDescuento (NEG, disparador REST / INT).
 *
 * Es el mismo handler el que implementa el puerto `CalculadorDeDescuento` que
 * consume `Pagos`: no hace falta un adaptador, porque la firma del puerto es la
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
final class ValidarCuponYCalcularDescuentoHandler implements CalculadorDeDescuento
{
    public function __construct(
        private readonly PromocionRepository $promociones,
        private readonly Clock $clock,
    ) {}

    public function __invoke(SolicitudDescuento $solicitud): ?DescuentoAplicado
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
        return $this($solicitud);
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
            $codigo = Promocion::normalizarCodigo($solicitud->codigoCupon);

            return $this->promociones->buscarPorCodigo($codigo)
                ?? throw new RecursoNoEncontrado("El cupon {$codigo} no existe");
        }

        if ($solicitud->promocionId !== null) {
            return $this->promociones->buscar($solicitud->promocionId)
                ?? throw new RecursoNoEncontrado("La promocion {$solicitud->promocionId} no existe");
        }

        return null;
    }

    /** Entre las promociones automaticas aplicables gana la de mayor descuento. */
    private function mejorAutomatica(
        SolicitudDescuento $solicitud,
        DateTimeImmutable $fecha,
        Money $subtotal,
    ): ?DescuentoAplicado {
        $mejor = null;
        $mejorMonto = Money::cero($subtotal->moneda());

        $candidatas = $this->promociones->vigentesSinCupon(
            $fecha,
            $solicitud->alojamientoId,
            $solicitud->anfitrionId,
        );

        foreach ($candidatas as $promocion) {
            if (! $promocion->aplicaA(
                $solicitud->noches,
                $fecha,
                $solicitud->alojamientoId,
                $solicitud->anfitrionId,
            )) {
                continue;
            }

            $monto = $promocion->calcularDescuento($subtotal);

            if ($monto->esMayorQue($mejorMonto)) {
                $mejor = $promocion;
                $mejorMonto = $monto;
            }
        }

        if ($mejor === null || $mejorMonto->esCero()) {
            return null;
        }

        return new DescuentoAplicado(
            promocionId: $mejor->promocionId(),
            descuentoCentavos: $mejorMonto->centavos(),
            moneda: $mejorMonto->moneda(),
            financiamiento: $mejor->financiadaPor(),
        );
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
}
