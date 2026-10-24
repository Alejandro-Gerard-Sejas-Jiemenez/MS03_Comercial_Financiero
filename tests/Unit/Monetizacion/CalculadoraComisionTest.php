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
        $this->calculadora = new CalculadoraComision();
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

    public function test_acepta_una_comision_de_monto_fijo(): void
    {
        $regla = ReglaMonetizacion::porMontoFijo(
            'r-fija',
            'Comision fija',
            Concepto::COMISION_ANFITRION,
            Money::deCentavos(5000),
            new DateTimeImmutable('2026-01-01'),
            'admin',
        );

        $desglose = $this->calculadora->calcular(Money::deCentavos(100000), Money::cero(), reglaComision: $regla);

        self::assertSame('50.00', $desglose->montoComision->aDecimal());
        self::assertSame('950.00', $desglose->netoAnfitrion->aDecimal());
        self::assertNull($desglose->porcentajeAplicado, 'un monto fijo no tiene porcentaje aplicado');
    }

    public function test_rechaza_un_descuento_mayor_al_subtotal(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('El descuento no puede superar el subtotal');

        $this->calculadora->calcular(Money::deCentavos(10000), Money::deCentavos(10001));
    }

    public function test_rechaza_un_descuento_negativo(): void
    {
        $this->expectException(DomainException::class);

        $this->calculadora->calcular(Money::deCentavos(10000), Money::deCentavos(-1));
    }

    public function test_rechaza_usar_un_cargo_al_cliente_como_comision_al_anfitrion(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no es de concepto COMISION_ANFITRION');

        $this->calculadora->calcular(
            Money::deCentavos(10000),
            Money::cero(),
            reglaComision: $this->cargoServicioDel('5'),
        );
    }

    public function test_rechaza_usar_una_comision_como_cargo_al_cliente(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no es de concepto CARGO_SERVICIO_CLIENTE');

        $this->calculadora->calcular(
            Money::deCentavos(10000),
            Money::cero(),
            reglaCargoServicio: $this->comisionDel('15'),
        );
    }

    public function test_rechaza_una_comision_de_monto_fijo_mayor_a_la_base(): void
    {
        $regla = ReglaMonetizacion::porMontoFijo(
            'r-grande',
            'Comision desproporcionada',
            Concepto::COMISION_ANFITRION,
            Money::deCentavos(20000),
            new DateTimeImmutable('2026-01-01'),
            'admin',
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('La comision no puede superar la base comisionable');

        $this->calculadora->calcular(Money::deCentavos(10000), Money::cero(), reglaComision: $regla);
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
