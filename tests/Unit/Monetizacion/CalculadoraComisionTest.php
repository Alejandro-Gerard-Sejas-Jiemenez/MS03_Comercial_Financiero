<?php

declare(strict_types=1);

namespace Tests\Unit\Monetizacion;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use Src\Monetizacion\Domain\CalculadoraComision;
use Src\Monetizacion\Domain\Concepto;
use Src\Monetizacion\Domain\FinanciamientoDescuento;
use Src\Monetizacion\Domain\ReglaMonetizacion;
use Src\Shared\Domain\Money;

/**
 * CU-MON-02: las formulas de dinero del microservicio, sin Laravel ni base de datos.
 */
final class CalculadoraComisionTest extends TestCase
{
    private CalculadoraComision $calculadora;

    protected function setUp(): void
    {
        $this->calculadora = new CalculadoraComision;
    }

    public function test_calcula_comision_cargo_de_servicio_y_neto_del_anfitrion(): void
    {
        $desglose = $this->calculadora->calcular(
            Money::deCentavos(100000),                  // 1000.00 de alojamiento
            Money::deCentavos(10000),                   // 100.00 de descuento
            FinanciamientoDescuento::ANFITRION,
            $this->comisionDel('15'),
            $this->cargoServicioDel('5'),
        );

        // base comisionable = 1000 - 100 = 900
        self::assertSame('900.00', $desglose->baseComisionable->aDecimal());
        // comision = 900 x 15 % = 135
        self::assertSame('135.00', $desglose->montoComision->aDecimal());
        // neto del anfitrion = 900 - 135 = 765
        self::assertSame('765.00', $desglose->netoAnfitrion->aDecimal());
        // cargo al cliente = 900 x 5 % = 45
        self::assertSame('45.00', $desglose->cargoServicio->aDecimal());
        // total que paga el cliente = 1000 - 100 + 45 = 945
        self::assertSame('945.00', $desglose->total->aDecimal());
        self::assertSame('15', $desglose->porcentajeAplicado);
    }

    public function test_si_la_plataforma_financia_el_descuento_la_comision_va_sobre_el_subtotal(): void
    {
        $desglose = $this->calculadora->calcular(
            Money::deCentavos(100000),
            Money::deCentavos(10000),
            FinanciamientoDescuento::PLATAFORMA,
            $this->comisionDel('15'),
        );

        // El anfitrion no absorbe el descuento: base = 1000, comision = 150
        self::assertSame('1000.00', $desglose->baseComisionable->aDecimal());
        self::assertSame('150.00', $desglose->montoComision->aDecimal());
        self::assertSame('850.00', $desglose->netoAnfitrion->aDecimal());
        // Lo que paga el cliente no cambia: sigue pagando 900
        self::assertSame('900.00', $desglose->total->aDecimal());
    }

    public function test_sin_reglas_no_hay_comision_ni_cargo(): void
    {
        $desglose = $this->calculadora->calcular(Money::deCentavos(50000), Money::cero());

        self::assertTrue($desglose->montoComision->esCero());
        self::assertTrue($desglose->cargoServicio->esCero());
        self::assertSame('500.00', $desglose->total->aDecimal());
        self::assertSame('500.00', $desglose->netoAnfitrion->aDecimal());
        self::assertNull($desglose->porcentajeAplicado);
    }

    public function test_rechaza_un_descuento_mayor_al_subtotal(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('El descuento no puede superar el subtotal');

        $this->calculadora->calcular(Money::deCentavos(10000), Money::deCentavos(10001));
    }

    private function comisionDel(string $porcentaje): ReglaMonetizacion
    {
        return ReglaMonetizacion::porPorcentaje(
            'r-comision',
            "Comision {$porcentaje} %",
            Concepto::COMISION_ANFITRION,
            $porcentaje,
            new DateTimeImmutable('2026-01-01'),
            'admin',
        );
    }

    private function cargoServicioDel(string $porcentaje): ReglaMonetizacion
    {
        return ReglaMonetizacion::porPorcentaje(
            'r-cargo',
            "Cargo de servicio {$porcentaje} %",
            Concepto::CARGO_SERVICIO_CLIENTE,
            $porcentaje,
            new DateTimeImmutable('2026-01-01'),
            'admin',
        );
    }
}
