<?php

declare(strict_types=1);

namespace Src\Financiero\Domain\Policy;

use DateTimeImmutable;
use Src\Financiero\Domain\Entity\Pago;
use Src\Financiero\Domain\Enum\MotivoReembolso;
use Src\Shared\Domain\Money;

/**
 * Cuanto se devuelve cuando se cancela una reserva.
 *
 * SUPUESTO: el documento del proyecto no define los tramos de la politica de
 * cancelacion. Los que estan aqui son una propuesta y hay que confirmarlos con
 * el negocio; estan en un solo lugar para que cambiarlos sea cambiar una clase
 * y sus pruebas, no buscar porcentajes repartidos por el codigo.
 *
 *   - 7 dias o mas antes del check-in: 100 %
 *   - entre 3 y 6 dias antes:           50 %
 *   - menos de 3 dias, o ya empezada:    0 %
 *
 * Cuando cancela el anfitrion se devuelve el 100 % sin importar la fecha.
 */
final class PoliticaDeReembolso
{
    private const DIAS_DEVOLUCION_TOTAL = 7;

    private const DIAS_DEVOLUCION_PARCIAL = 3;

    private const PORCENTAJE_PARCIAL = '50';

    public function montoADevolver(
        Money $totalPagado,
        DateTimeImmutable $fechaCancelacion,
        DateTimeImmutable $fechaCheckin,
        MotivoReembolso $motivo = MotivoReembolso::CANCELACION_CLIENTE,
    ): Money {
        // Si la culpa no es del cliente, se le devuelve todo.
        if ($motivo !== MotivoReembolso::CANCELACION_CLIENTE) {
            return $totalPagado;
        }

        $dias = $this->diasDeAnticipacion($fechaCancelacion, $fechaCheckin);

        if ($dias >= self::DIAS_DEVOLUCION_TOTAL) {
            return $totalPagado;
        }

        if ($dias >= self::DIAS_DEVOLUCION_PARCIAL) {
            return $totalPagado->porPorcentaje(self::PORCENTAJE_PARCIAL);
        }

        return Money::cero($totalPagado->moneda());
    }

    /** Negativo si la cancelacion llega despues del check-in. */
    /**
     * Lo que se reembolsa al cancelar una reserva pagada: el monto forzado por un
     * ADMIN o el que dice la politica, sin pasar nunca de lo que queda por devolver.
     * Devuelve null cuando no hay nada que devolver (monto cero).
     */
    public function montoSolicitable(
        Pago $pago,
        Money $yaReembolsado,
        ?Money $montoForzado,
        DateTimeImmutable $fechaCancelacion,
        DateTimeImmutable $fechaCheckin,
        MotivoReembolso $motivo,
    ): ?Money {
        $saldo = $pago->saldoReembolsable($yaReembolsado);
        $monto = $montoForzado ?? $this->montoADevolver($pago->total(), $fechaCancelacion, $fechaCheckin, $motivo);

        if ($monto->esMayorQue($saldo)) {
            $monto = $saldo;
        }

        return $monto->esPositivo() ? $monto : null;
    }

    private function diasDeAnticipacion(
        DateTimeImmutable $fechaCancelacion,
        DateTimeImmutable $fechaCheckin,
    ): int {
        $desde = new DateTimeImmutable($fechaCancelacion->format('Y-m-d'));
        $hasta = new DateTimeImmutable($fechaCheckin->format('Y-m-d'));
        $diferencia = $desde->diff($hasta);

        return (int) $diferencia->days * ($diferencia->invert === 1 ? -1 : 1);
    }
}
