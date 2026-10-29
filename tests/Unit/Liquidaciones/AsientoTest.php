<?php

declare(strict_types=1);

namespace Tests\Unit\Liquidaciones;

use DomainException;
use PHPUnit\Framework\TestCase;
use Src\Liquidaciones\Domain\Asiento;
use Src\Liquidaciones\Domain\CuentaContable;
use Src\Liquidaciones\Domain\MovimientoContable;
use Src\Liquidaciones\Domain\OrigenMovimiento;
use Src\Liquidaciones\Domain\TipoMovimiento;
use Src\Shared\Domain\Money;

/**
 * Partida doble: SUM(DEBITO) = SUM(CREDITO) por asiento.
 *
 * Es la regla que la base de datos no puede expresar en un CHECK, asi que si
 * esta prueba no existiera nada impediria guardar un libro descuadrado.
 */
final class AsientoTest extends TestCase
{
    public function test_no_se_puede_construir_un_asiento_descuadrado(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Asiento desbalanceado');

        Asiento::de('asiento-1', [
            new MovimientoContable(
                'asiento-1',
                CuentaContable::PASARELA,
                TipoMovimiento::DEBITO,
                Money::deCentavos(100000),
                OrigenMovimiento::PAGO,
                'pago-1',
            ),
            new MovimientoContable(
                'asiento-1',
                CuentaContable::INGRESO_COMISION,
                TipoMovimiento::CREDITO,
                Money::deCentavos(15000),
                OrigenMovimiento::PAGO,
                'pago-1',
            ),
        ]);
    }

    public function test_el_asiento_de_un_pago_capturado_reparte_el_total_cobrado(): void
    {
        // 1000 de alojamiento - 100 de descuento + 45 de cargo = 945 cobrados.
        // De los 900 del anfitrion, 135 son comision y 765 le quedan a el.
        $asiento = Asiento::porPagoCapturado(
            asientoId: 'asiento-1',
            pagoId: 'pago-1',
            anfitrionId: 'anfitrion-1',
            total: Money::deCentavos(94500),
            comision: Money::deCentavos(13500),
            cargoServicio: Money::deCentavos(4500),
            netoAnfitrion: Money::deCentavos(76500),
        );

        $movimientos = $asiento->movimientos();
        self::assertCount(4, $movimientos);

        $porCuenta = [];

        foreach ($movimientos as $movimiento) {
            $porCuenta[$movimiento->cuenta->value] = [
                $movimiento->tipo->value,
                $movimiento->monto->aDecimal(),
            ];
        }

        self::assertSame(['DEBITO', '945.00'], $porCuenta['PASARELA']);
        self::assertSame(['CREDITO', '135.00'], $porCuenta['INGRESO_COMISION']);
        self::assertSame(['CREDITO', '45.00'], $porCuenta['INGRESO_CARGO_SERVICIO']);
        self::assertSame(['CREDITO', '765.00'], $porCuenta['POR_PAGAR_ANFITRION']);
    }

    public function test_el_asiento_de_una_comision_revertida_cuadra_contra_reembolsos(): void
    {
        $asiento = Asiento::porComisionRevertida(
            asientoId: 'asiento-2',
            comisionId: 'comision-1',
            anfitrionId: 'anfitrion-1',
            comision: Money::deCentavos(13500),
            netoAnfitrion: Money::deCentavos(76500),
        );

        $movimientos = $asiento->movimientos();
        self::assertCount(3, $movimientos);

        $reembolsos = array_values(array_filter(
            $movimientos,
            static fn (MovimientoContable $m): bool => $m->cuenta === CuentaContable::REEMBOLSOS,
        ));

        self::assertSame('900.00', $reembolsos[0]->monto->aDecimal());
        self::assertSame(TipoMovimiento::CREDITO, $reembolsos[0]->tipo);
    }

    public function test_el_asiento_de_una_liquidacion_pagada_cancela_la_deuda(): void
    {
        $asiento = Asiento::porLiquidacionPagada(
            asientoId: 'asiento-3',
            liquidacionId: 'liq-1',
            anfitrionId: 'anfitrion-1',
            totalNeto: Money::deCentavos(76500),
        );

        $movimientos = $asiento->movimientos();
        self::assertCount(2, $movimientos);
        self::assertSame(CuentaContable::POR_PAGAR_ANFITRION, $movimientos[0]->cuenta);
        self::assertSame(TipoMovimiento::DEBITO, $movimientos[0]->tipo);
        self::assertSame(CuentaContable::PASARELA, $movimientos[1]->cuenta);
        self::assertSame(TipoMovimiento::CREDITO, $movimientos[1]->tipo);
        self::assertSame(OrigenMovimiento::LIQUIDACION, $movimientos[0]->origenTipo);
    }
}
