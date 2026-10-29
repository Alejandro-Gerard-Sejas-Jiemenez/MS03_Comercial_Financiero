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
