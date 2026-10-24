<?php

declare(strict_types=1);

namespace Tests\Unit\Promociones;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use Src\Monetizacion\Domain\FinanciamientoDescuento;
use Src\Promociones\Domain\EstadoPromocion;
use Src\Promociones\Domain\Event\PromocionAplicada;
use Src\Promociones\Domain\Promocion;
use Src\Promociones\Domain\RolCreador;
use Src\Promociones\Domain\TipoDescuento;
use Src\Promociones\Domain\TipoPromocion;
use Src\Shared\Domain\Money;

/**
 * F09 / CU-PRO-02 y CU-PRO-03: las reglas de una promocion, sin Laravel ni
 * base de datos.
 */
final class PromocionTest extends TestCase
{
    private const HOY = '2026-10-23 12:00:00';

    public function test_una_promocion_vencida_no_da_descuento(): void
    {
        $promocion = $this->promocionActiva(
            vigenteDesde: '2026-09-01 00:00:00',
            vigenteHasta: '2026-10-01 00:00:00',
        );

        self::assertFalse($promocion->aplicaA(3, $this->hoy(), null, 'anf-1'));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no esta vigente');
        $promocion->asegurarAplicableA(3, $this->hoy(), null, 'anf-1');
    }

    public function test_una_promocion_pausada_no_da_descuento(): void
    {
        $promocion = $this->promocionActiva();
        $promocion->pausar();

        self::assertSame(EstadoPromocion::PAUSADA, $promocion->estado());
        self::assertFalse($promocion->aplicaA(3, $this->hoy(), null, 'anf-1'));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('PAUSADA');
        $promocion->asegurarAplicableA(3, $this->hoy(), null, 'anf-1');
    }

    public function test_por_debajo_de_las_noches_minimas_no_da_descuento(): void
    {
        $promocion = $this->promocionActiva(minNoches: 4);

        self::assertFalse($promocion->aplicaA(3, $this->hoy(), null, 'anf-1'));
        self::assertTrue($promocion->aplicaA(4, $this->hoy(), null, 'anf-1'));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('4 noches');
        $promocion->asegurarAplicableA(3, $this->hoy(), null, 'anf-1');
    }

    public function test_al_llegar_al_tope_de_usos_no_se_puede_registrar_otro_uso(): void
    {
        $promocion = $this->promocionActiva(maxUsos: 2);

        $promocion->registrarUso($this->hoy(), Money::deCentavos(1000));
        $promocion->registrarUso($this->hoy(), Money::deCentavos(1000));

        self::assertSame(2, $promocion->usosActuales());
        self::assertFalse($promocion->tieneUsosDisponibles());
        self::assertFalse($promocion->aplicaA(3, $this->hoy(), null, 'anf-1'));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('tope de usos');
        $promocion->registrarUso($this->hoy(), Money::deCentavos(1000));
    }

    public function test_un_descuento_monto_fijo_no_puede_superar_el_subtotal(): void
    {
        $promocion = $this->promocionActiva(
            tipoDescuento: TipoDescuento::MONTO_FIJO,
            valor: '150.00',
        );

        // El monto fijo supera el subtotal: el descuento se topa al subtotal.
        self::assertSame('100.00', $promocion->calcularDescuento(Money::deCentavos(10000))->aDecimal());
        self::assertSame('150.00', $promocion->calcularDescuento(Money::deCentavos(30000))->aDecimal());
    }

    public function test_un_descuento_porcentaje_redondea_el_medio_centavo_hacia_arriba(): void
    {
        $promocion = $this->promocionActiva(valor: '50.00');

        // 100.01 x 50 % = 50.005 -> 50.01
        self::assertSame('50.01', $promocion->calcularDescuento(Money::deCentavos(10001))->aDecimal());
    }

    public function test_registrar_uso_emite_promocion_aplicada(): void
    {
        $promocion = $this->promocionActiva(codigo: 'VERANO10');

        $promocion->registrarUso(
            $this->hoy(),
            Money::deCentavos(2550),
            reservaId: 'res-1',
            pagoId: 'pag-1',
            sagaId: 'saga-1',
        );

        $eventos = $promocion->sacarEventos();
        self::assertCount(1, $eventos);

        $evento = $eventos[0];
        self::assertInstanceOf(PromocionAplicada::class, $evento);
        self::assertSame('promocion.aplicada', $evento->tipoEvento());
        self::assertSame('promocion', $evento->agregadoTipo());
        self::assertSame('promo-1', $evento->agregadoId());
        self::assertSame('saga-1', $evento->sagaId());

        $payload = $evento->payload();
        self::assertSame('promo-1', $payload['promocion_id']);
        self::assertSame('VERANO10', $payload['codigo']);
        self::assertSame('25.50', $payload['descuento']);
        self::assertSame('BOB', $payload['moneda']);
        self::assertSame('ANFITRION', $payload['financiada_por']);
        self::assertSame(1, $payload['usos_actuales']);
        self::assertSame('res-1', $payload['reserva_id']);
        self::assertSame('pag-1', $payload['pago_id']);

        // Los eventos se entregan una sola vez.
        self::assertSame([], $promocion->sacarEventos());
    }

    public function test_si_tiene_alojamientos_asignados_solo_aplica_a_esos(): void
    {
        $promocion = $this->promocionActiva();
        $promocion->asignarAlojamientos(['aloj-1', 'aloj-2']);

        self::assertTrue($promocion->aplicaA(3, $this->hoy(), 'aloj-1', 'anf-1'));
        self::assertFalse($promocion->aplicaA(3, $this->hoy(), 'aloj-9', 'anf-1'));
        self::assertSame(['aloj-1', 'aloj-2'], $promocion->alojamientos());
    }

    public function test_sin_alojamientos_asignados_aplica_a_todos_los_del_anfitrion(): void
    {
        $promocion = $this->promocionActiva();

        self::assertTrue($promocion->aplicaA(3, $this->hoy(), 'aloj-9', 'anf-1'));
        // Un alojamiento de otro anfitrion queda fuera.
        self::assertFalse($promocion->aplicaA(3, $this->hoy(), 'aloj-9', 'anf-2'));
    }

    public function test_una_promocion_de_la_plataforma_aplica_a_cualquier_anfitrion(): void
    {
        $promocion = $this->promocionActiva(anfitrionId: null);

        self::assertTrue($promocion->aplicaA(3, $this->hoy(), 'aloj-9', 'anf-7'));
    }

    public function test_solo_vence_si_ya_paso_su_fecha(): void
    {
        $vigente = $this->promocionActiva();
        $vencida = $this->promocionActiva(
            vigenteDesde: '2026-09-01 00:00:00',
            vigenteHasta: '2026-10-01 00:00:00',
        );

        $vencida->vencer($this->hoy());
        self::assertSame(EstadoPromocion::VENCIDA, $vencida->estado());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('sigue vigente');
        $vigente->vencer($this->hoy());
    }

    public function test_el_ciclo_de_estados_de_la_promocion(): void
    {
        $promocion = $this->promocionBorrador();
        self::assertSame(EstadoPromocion::BORRADOR, $promocion->estado());

        $promocion->activar();
        self::assertSame(EstadoPromocion::ACTIVA, $promocion->estado());

        $promocion->pausar();
        $promocion->activar();
        self::assertSame(EstadoPromocion::ACTIVA, $promocion->estado());

        $promocion->cancelar();
        self::assertSame(EstadoPromocion::CANCELADA, $promocion->estado());

        $this->expectException(DomainException::class);
        $promocion->activar();
    }

    public function test_un_porcentaje_mayor_a_cien_es_invalido(): void
    {
        $this->expectException(DomainException::class);
        $this->promocionBorrador(valor: '120.00');
    }

    public function test_el_valor_de_la_promocion_debe_ser_positivo(): void
    {
        $this->expectException(DomainException::class);
        $this->promocionBorrador(valor: '0.00');
    }

    public function test_la_vigencia_no_puede_terminar_antes_de_empezar(): void
    {
        $this->expectException(DomainException::class);
        $this->promocionBorrador(
            vigenteDesde: '2026-11-01 00:00:00',
            vigenteHasta: '2026-10-01 00:00:00',
        );
    }

    private function hoy(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::HOY);
    }

    private function promocionActiva(
        TipoDescuento $tipoDescuento = TipoDescuento::PORCENTAJE,
        string $valor = '10.00',
        string $vigenteDesde = '2026-10-01 00:00:00',
        string $vigenteHasta = '2026-12-31 23:59:59',
        int $minNoches = 1,
        ?int $maxUsos = null,
        ?string $codigo = null,
        ?string $anfitrionId = 'anf-1',
    ): Promocion {
        $promocion = $this->promocionBorrador(
            $tipoDescuento,
            $valor,
            $vigenteDesde,
            $vigenteHasta,
            $minNoches,
            $maxUsos,
            $codigo,
            $anfitrionId,
        );
        $promocion->activar();

        return $promocion;
    }

    private function promocionBorrador(
        TipoDescuento $tipoDescuento = TipoDescuento::PORCENTAJE,
        string $valor = '10.00',
        string $vigenteDesde = '2026-10-01 00:00:00',
        string $vigenteHasta = '2026-12-31 23:59:59',
        int $minNoches = 1,
        ?int $maxUsos = null,
        ?string $codigo = null,
        ?string $anfitrionId = 'anf-1',
    ): Promocion {
        return Promocion::crear(
            promocionId: 'promo-1',
            nombre: 'Promocion de prueba',
            tipo: TipoPromocion::TEMPORADA,
            tipoDescuento: $tipoDescuento,
            valor: $valor,
            vigenteDesde: new DateTimeImmutable($vigenteDesde),
            vigenteHasta: new DateTimeImmutable($vigenteHasta),
            creadaPorRol: RolCreador::ANFITRION,
            creadaPor: 'usr-1',
            codigo: $codigo,
            anfitrionId: $anfitrionId,
            financiadaPor: FinanciamientoDescuento::ANFITRION,
            minNoches: $minNoches,
            maxUsos: $maxUsos,
        );
    }
}
