<?php

declare(strict_types=1);

namespace Tests\Unit\Liquidaciones;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use Src\Liquidaciones\Domain\Comision;
use Src\Liquidaciones\Domain\EstadoComision;
use Src\Liquidaciones\Domain\EstadoLiquidacion;
use Src\Liquidaciones\Domain\Liquidacion;
use Src\Shared\Domain\Money;

/**
 * Ciclo de vida de la comision y de la liquidacion. Dominio puro.
 */
final class ComisionYLiquidacionTest extends TestCase
{
    public function test_la_comision_calcula_el_neto_del_anfitrion(): void
    {
        $comision = $this->comision(base: 90000, monto: 13500);

        self::assertSame('900.00', $comision->baseComisionable()->aDecimal());
        self::assertSame('135.00', $comision->montoComision()->aDecimal());
        // neto = base - comision
        self::assertSame('765.00', $comision->montoNetoAnfitrion()->aDecimal());
        self::assertSame(EstadoComision::RETENIDA, $comision->estado());
    }

    public function test_la_comision_no_puede_superar_la_base(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('La comision no puede superar la base comisionable');

        $this->comision(base: 10000, monto: 10001);
    }

    public function test_guarda_el_porcentaje_aplicado_como_snapshot(): void
    {
        $comision = $this->comision(porcentaje: '15.50');

        // Si manana cambia la regla, este historico no se mueve.
        self::assertSame('15.50', $comision->porcentajeAplicado());
    }

    public function test_una_comision_retenida_pasa_a_liquidable_con_su_plazo(): void
    {
        $comision = $this->comision();

        $comision->marcarLiquidable(new DateTimeImmutable('2026-11-10'));

        self::assertSame(EstadoComision::LIQUIDABLE, $comision->estado());
        self::assertFalse(
            $comision->sePuedeLiquidarEn(new DateTimeImmutable('2026-11-09')),
            'antes del plazo no se liquida',
        );
        self::assertTrue($comision->sePuedeLiquidarEn(new DateTimeImmutable('2026-11-10')));
        self::assertTrue($comision->sePuedeLiquidarEn(new DateTimeImmutable('2026-11-20')));
    }

    public function test_una_comision_revertida_no_se_vuelve_liquidable(): void
    {
        $comision = $this->comision();
        $comision->revertir();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('esta REVERTIDA y no se puede liquidar');

        $comision->marcarLiquidable(new DateTimeImmutable('2026-11-10'));
    }

    public function test_solo_una_comision_liquidable_entra_en_una_liquidacion(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Solo se liquida una comision LIQUIDABLE');

        $this->comision()->asignarALiquidacion('liq-1');
    }

    public function test_una_comision_liquidada_se_libera_si_la_liquidacion_se_rechaza(): void
    {
        $comision = $this->comision();
        $comision->marcarLiquidable(new DateTimeImmutable('2026-11-10'));
        $comision->asignarALiquidacion('liq-1');

        self::assertSame('liq-1', $comision->liquidacionId());

        $comision->liberar();

        self::assertSame(EstadoComision::LIQUIDABLE, $comision->estado());
        self::assertNull($comision->liquidacionId(), 'queda libre para la corrida siguiente');
    }

    public function test_una_comision_ya_liquidada_no_se_revierte(): void
    {
        $comision = $this->comision();
        $comision->marcarLiquidable(new DateTimeImmutable('2026-11-10'));
        $comision->asignarALiquidacion('liq-1');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('se descuenta de la liquidacion siguiente');

        $comision->revertir();
    }

    public function test_la_liquidacion_calcula_neto_igual_a_bruto_menos_comisiones_menos_reembolsos(): void
    {
        $liquidacion = $this->liquidacion(bruto: 120000, comisiones: 18000, reembolsos: 25000);

        self::assertSame('770.00', $liquidacion->totalNeto()->aDecimal());
        self::assertSame(EstadoLiquidacion::GENERADA, $liquidacion->estado());
    }

    public function test_no_se_genera_una_liquidacion_de_monto_negativo(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('los reembolsos superan lo que se le debe');

        $this->liquidacion(bruto: 100000, comisiones: 15000, reembolsos: 90000);
    }

    public function test_no_se_genera_una_liquidacion_de_monto_cero(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('monto cero');

        $this->liquidacion(bruto: 100000, comisiones: 100000, reembolsos: 0);
    }

    public function test_no_se_aprueba_una_liquidacion_sin_metodo_de_cobro(): void
    {
        $liquidacion = $this->liquidacion(metodoId: null);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('sin metodo de cobro verificado');

        $liquidacion->aprobar('admin-1');
    }

    public function test_solo_se_paga_una_liquidacion_aprobada(): void
    {
        $liquidacion = $this->liquidacion();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Solo se paga una liquidacion APROBADA');

        $liquidacion->pagar('transf-1');
    }

    public function test_pagar_emite_liquidacion_pagada(): void
    {
        $liquidacion = $this->liquidacion(bruto: 120000, comisiones: 18000, reembolsos: 0);
        $liquidacion->aprobar('admin-1');

        $liquidacion->pagar('TRANSF-0001');

        self::assertSame(EstadoLiquidacion::PAGADA, $liquidacion->estado());

        $eventos = $liquidacion->sacarEventos();
        self::assertCount(1, $eventos);
        self::assertSame('liquidacion.pagada', $eventos[0]->tipoEvento());
        self::assertSame('liquidacion', $eventos[0]->agregadoTipo());

        $payload = $eventos[0]->payload();
        self::assertSame('1020.00', $payload['total_neto']);
        self::assertSame('TRANSF-0001', $payload['referencia_transferencia']);
        self::assertSame('anfitrion-1', $payload['anfitrion_id']);
    }

    public function test_pagar_exige_la_referencia_de_la_transferencia(): void
    {
        $liquidacion = $this->liquidacion();
        $liquidacion->aprobar('admin-1');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('referencia de la transferencia');

        $liquidacion->pagar('   ');
    }

    public function test_una_liquidacion_pagada_no_se_anula(): void
    {
        $liquidacion = $this->liquidacion();
        $liquidacion->aprobar('admin-1');
        $liquidacion->pagar('TRANSF-1');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No se anula una liquidacion en estado PAGADA');

        $liquidacion->anular('error');
    }

    public function test_rechazar_exige_motivo(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('exige un motivo');

        $this->liquidacion()->rechazar('admin-1', '  ');
    }

    private function comision(
        int $base = 90000,
        int $monto = 13500,
        ?string $porcentaje = '15',
    ): Comision {
        return Comision::registrar(
            comisionId: 'comision-1',
            pagoId: 'pago-1',
            reservaId: 'reserva-1',
            anfitrionId: 'anfitrion-1',
            baseComisionable: Money::deCentavos($base),
            montoComision: Money::deCentavos($monto),
            porcentajeAplicado: $porcentaje,
        );
    }

    private function liquidacion(
        int $bruto = 120000,
        int $comisiones = 18000,
        int $reembolsos = 0,
        ?string $metodoId = 'metodo-1',
    ): Liquidacion {
        return Liquidacion::generar(
            liquidacionId: 'liq-1',
            anfitrionId: 'anfitrion-1',
            periodoDesde: new DateTimeImmutable('2026-10-01'),
            periodoHasta: new DateTimeImmutable('2026-10-31'),
            totalBruto: Money::deCentavos($bruto),
            totalComisiones: Money::deCentavos($comisiones),
            totalReembolsos: Money::deCentavos($reembolsos),
            metodoId: $metodoId,
        );
    }
}
