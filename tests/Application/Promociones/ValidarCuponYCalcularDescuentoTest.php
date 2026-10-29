<?php

declare(strict_types=1);

namespace Tests\Application\Promociones;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use Src\Monetizacion\Domain\FinanciamientoDescuento;
use Src\Promociones\Application\Port\SolicitudDescuento;
use Src\Promociones\Application\UseCase\ValidarCuponYCalcularDescuento\ValidarCuponYCalcularDescuentoHandler;
use Src\Promociones\Domain\Promocion;
use Src\Promociones\Domain\RolCreador;
use Src\Promociones\Domain\TipoDescuento;
use Src\Promociones\Domain\TipoPromocion;
use Tests\Doubles\FrozenClock;
use Tests\Doubles\InMemoryPromocionRepository;

/**
 * CU-PRO-02 ValidarCuponYCalcularDescuento, con repositorio en memoria.
 */
final class ValidarCuponYCalcularDescuentoTest extends TestCase
{
    private InMemoryPromocionRepository $promociones;

    private FrozenClock $clock;

    private ValidarCuponYCalcularDescuentoHandler $handler;

    protected function setUp(): void
    {
        $this->promociones = new InMemoryPromocionRepository;
        $this->clock = new FrozenClock('2026-10-23 12:00:00');
        $this->handler = new ValidarCuponYCalcularDescuentoHandler($this->promociones, $this->clock);
    }

    public function test_un_cupon_vigente_devuelve_el_descuento_calculado(): void
    {
        $this->guardarActiva('promo-1', codigo: 'VERANO10', valor: '10.00');

        $descuento = ($this->handler)(new SolicitudDescuento(
            subtotalCentavos: 100000,
            noches: 3,
            codigoCupon: 'verano10',
            anfitrionId: 'anf-1',
        ));

        self::assertNotNull($descuento);
        self::assertSame('promo-1', $descuento->promocionId);
        self::assertSame(10000, $descuento->descuentoCentavos);
        self::assertSame('BOB', $descuento->moneda);
        self::assertSame(FinanciamientoDescuento::ANFITRION, $descuento->financiamiento);
    }

    public function test_un_cupon_de_promocion_vencida_no_da_descuento(): void
    {
        $this->guardarActiva(
            'promo-1',
            codigo: 'VIEJO',
            vigenteDesde: '2026-09-01 00:00:00',
            vigenteHasta: '2026-10-01 00:00:00',
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no esta vigente');

        ($this->handler)(new SolicitudDescuento(
            subtotalCentavos: 100000,
            noches: 3,
            codigoCupon: 'VIEJO',
            anfitrionId: 'anf-1',
        ));
    }

    public function test_un_cupon_inexistente_falla(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no existe');

        ($this->handler)(new SolicitudDescuento(
            subtotalCentavos: 100000,
            noches: 3,
            codigoCupon: 'NO-EXISTE',
        ));
    }

    public function test_un_descuento_monto_fijo_no_supera_el_subtotal(): void
    {
        $this->guardarActiva(
            'promo-1',
            codigo: 'FIJO200',
            tipoDescuento: TipoDescuento::MONTO_FIJO,
            valor: '200.00',
        );

        $descuento = ($this->handler)(new SolicitudDescuento(
            subtotalCentavos: 15000,
            noches: 2,
            codigoCupon: 'FIJO200',
            anfitrionId: 'anf-1',
        ));

        self::assertNotNull($descuento);
        self::assertSame(15000, $descuento->descuentoCentavos);
    }

    public function test_sin_cupon_se_aplica_la_promocion_automatica_mas_conveniente(): void
    {
        $this->guardarActiva('promo-chica', valor: '5.00');
        $this->guardarActiva('promo-grande', valor: '15.00');

        $descuento = ($this->handler)(new SolicitudDescuento(
            subtotalCentavos: 100000,
            noches: 3,
            alojamientoId: 'aloj-1',
            anfitrionId: 'anf-1',
        ));

        self::assertNotNull($descuento);
        self::assertSame('promo-grande', $descuento->promocionId);
        self::assertSame(15000, $descuento->descuentoCentavos);
    }

    public function test_si_tiene_alojamientos_asignados_solo_aplica_a_esos(): void
    {
        $promocion = $this->guardarActiva('promo-1');
        $promocion->asignarAlojamientos(['aloj-1']);

        self::assertNotNull(($this->handler)(new SolicitudDescuento(
            subtotalCentavos: 100000,
            noches: 3,
            alojamientoId: 'aloj-1',
            anfitrionId: 'anf-1',
        )));

        self::assertNull(($this->handler)(new SolicitudDescuento(
            subtotalCentavos: 100000,
            noches: 3,
            alojamientoId: 'aloj-9',
            anfitrionId: 'anf-1',
        )));
    }

    private function guardarActiva(
        string $promocionId,
        ?string $codigo = null,
        TipoDescuento $tipoDescuento = TipoDescuento::PORCENTAJE,
        string $valor = '10.00',
        string $vigenteDesde = '2026-10-01 00:00:00',
        string $vigenteHasta = '2026-12-31 23:59:59',
        int $minNoches = 1,
        ?int $maxUsos = null,
        ?string $anfitrionId = 'anf-1',
        FinanciamientoDescuento $financiadaPor = FinanciamientoDescuento::ANFITRION,
    ): Promocion {
        $promocion = Promocion::crear(
            promocionId: $promocionId,
            nombre: 'Promocion '.$promocionId,
            tipo: $codigo === null ? TipoPromocion::TEMPORADA : TipoPromocion::CUPON,
            tipoDescuento: $tipoDescuento,
            valor: $valor,
            vigenteDesde: new DateTimeImmutable($vigenteDesde),
            vigenteHasta: new DateTimeImmutable($vigenteHasta),
            creadaPorRol: RolCreador::ADMIN,
            creadaPor: 'usr-1',
            codigo: $codigo,
            anfitrionId: $anfitrionId,
            financiadaPor: $financiadaPor,
            minNoches: $minNoches,
            maxUsos: $maxUsos,
        );
        $promocion->activar();
        $this->promociones->guardar($promocion);

        return $promocion;
    }
}
