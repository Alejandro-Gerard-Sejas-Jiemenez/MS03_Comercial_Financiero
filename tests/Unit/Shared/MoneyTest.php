<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use DomainException;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Money;

/**
 * Reglas de dinero de MS-03. Dominio puro: sin Laravel y sin base de datos.
 */
final class MoneyTest extends TestCase
{
    public function test_total_de_un_pago_es_subtotal_menos_descuento_mas_cargo(): void
    {
        $total = Money::deCentavos(50000)
            ->menos(Money::deCentavos(5000))
            ->mas(Money::deCentavos(3500));

        self::assertSame(48500, $total->centavos());
        self::assertSame('485.00', $total->aDecimal());
    }

    public function test_neto_de_una_liquidacion_es_bruto_menos_comisiones_menos_reembolsos(): void
    {
        $neto = Money::deCentavos(120000)
            ->menos(Money::deCentavos(18000))
            ->menos(Money::deCentavos(25000));

        self::assertSame(77000, $neto->centavos());
    }

    public function test_las_operaciones_no_modifican_el_monto_original(): void
    {
        $subtotal = Money::deCentavos(10000);
        $subtotal->mas(Money::deCentavos(5000));

        self::assertSame(10000, $subtotal->centavos());
    }

    public function test_lee_el_formato_decimal_de_la_base_de_datos(): void
    {
        self::assertSame(123450, Money::deDecimal('1234.50')->centavos());
        self::assertSame(123450, Money::deDecimal('1234.5')->centavos());
        self::assertSame(123400, Money::deDecimal('1234')->centavos());
        self::assertSame(5, Money::deDecimal('0.05')->centavos());
        self::assertSame(-2550, Money::deDecimal('-25.50')->centavos());
    }

    public function test_comision_es_la_base_por_el_porcentaje(): void
    {
        $comision = Money::deCentavos(100000)->porPorcentaje('15');

        self::assertSame(15000, $comision->centavos());
    }

    public function test_el_porcentaje_redondea_el_medio_centavo_hacia_arriba(): void
    {
        // 10.00 x 12.35% = 1.235 -> 1.24
        self::assertSame(124, Money::deCentavos(1000)->porPorcentaje('12.35')->centavos());
        // 10.00 x 12.34% = 1.234 -> 1.23
        self::assertSame(123, Money::deCentavos(1000)->porPorcentaje('12.34')->centavos());
    }

    public function test_no_mezcla_monedas(): void
    {
        $this->expectException(DomainException::class);

        Money::deCentavos(10000, 'BOB')->mas(Money::deCentavos(10000, 'USD'));
    }
}
