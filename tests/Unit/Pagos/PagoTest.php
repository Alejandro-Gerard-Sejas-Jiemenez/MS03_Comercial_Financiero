<?php

declare(strict_types=1);

namespace Tests\Unit\Pagos;

use DomainException;
use PHPUnit\Framework\TestCase;
use Src\Pagos\Domain\EstadoPago;
use Src\Pagos\Domain\Event\PagoCapturado;
use Src\Pagos\Domain\Event\PagoFallido;
use Src\Pagos\Domain\MetodoPago;
use Src\Pagos\Domain\Pago;
use Src\Shared\Domain\Money;

/**
 * Reglas de la entidad `Pago`: dominio puro, sin Laravel ni base de datos.
 */
final class PagoTest extends TestCase
{
    public function test_el_total_es_subtotal_menos_descuento_mas_cargo_de_servicio(): void
    {
        $pago = $this->pago(subtotal: 100000, descuento: 10000, cargoServicio: 4500);

        self::assertSame('945.00', $pago->total()->aDecimal());
    }

    public function test_el_monto_del_anfitrion_no_incluye_el_cargo_de_servicio(): void
    {
        $pago = $this->pago(subtotal: 100000, descuento: 10000, cargoServicio: 4500);

        // El cargo de servicio es ingreso de la plataforma, no del anfitrion.
        self::assertSame('900.00', $pago->montoDelAnfitrion()->aDecimal());
    }

    public function test_nace_pendiente(): void
    {
        self::assertSame(EstadoPago::PENDIENTE, $this->pago()->estado());
        self::assertSame([], $this->pago()->sacarEventos());
    }

    public function test_rechaza_un_descuento_mayor_al_subtotal(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('El descuento no puede superar el subtotal');

        $this->pago(subtotal: 10000, descuento: 10001);
    }

    public function test_rechaza_un_descuento_negativo(): void
    {
        $this->expectException(DomainException::class);

        $this->pago(descuento: -1);
    }

    public function test_rechaza_un_pago_sin_idempotency_key(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Idempotency-Key');

        $this->pago(idempotencyKey: '   ');
    }

    public function test_capturar_emite_pago_capturado_con_el_desglose(): void
    {
        $pago = $this->pago(subtotal: 100000, descuento: 10000, cargoServicio: 4500);

        $pago->capturar('ref-pasarela-1', 'SIMULADA');

        self::assertSame(EstadoPago::CAPTURADO, $pago->estado());
        self::assertSame('SIMULADA', $pago->pasarela());
        self::assertSame('ref-pasarela-1', $pago->referenciaPasarela());

        $eventos = $pago->sacarEventos();
        self::assertCount(1, $eventos);
        self::assertInstanceOf(PagoCapturado::class, $eventos[0]);
        self::assertSame('pago.capturado', $eventos[0]->tipoEvento());
        self::assertSame('pago', $eventos[0]->agregadoTipo());

        $payload = $eventos[0]->payload();
        self::assertSame('945.00', $payload['monto_total']);
        self::assertSame('1000.00', $payload['subtotal_alojamiento']);
        self::assertSame('100.00', $payload['descuento']);
        self::assertSame('45.00', $payload['cargo_servicio']);
        self::assertSame('BOB', $payload['moneda']);
    }

    public function test_sacar_eventos_los_descarta_para_no_publicarlos_dos_veces(): void
    {
        $pago = $this->pago();
        $pago->capturar('ref-1');

        self::assertCount(1, $pago->sacarEventos());
        self::assertSame([], $pago->sacarEventos());
    }

    public function test_no_se_captura_dos_veces_el_mismo_pago(): void
    {
        $pago = $this->pago();
        $pago->capturar('ref-1');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No se puede capturar un pago en estado CAPTURADO');

        $pago->capturar('ref-2');
    }

    public function test_un_pago_autorizado_todavia_se_puede_capturar(): void
    {
        $pago = $this->pago();
        $pago->autorizar('auth-1');

        self::assertSame(EstadoPago::AUTORIZADO, $pago->estado());

        $pago->capturar('cap-1');

        self::assertSame(EstadoPago::CAPTURADO, $pago->estado());
    }

    public function test_marcar_fallido_emite_pago_fallido_para_que_ms02_compense(): void
    {
        $pago = $this->pago();

        $pago->marcarFallido('tarjeta rechazada');

        self::assertSame(EstadoPago::FALLIDO, $pago->estado());
        $eventos = $pago->sacarEventos();
        self::assertInstanceOf(PagoFallido::class, $eventos[0]);
        self::assertSame('pago.fallido', $eventos[0]->tipoEvento());
        self::assertSame('tarjeta rechazada', $eventos[0]->payload()['motivo']);
    }

    public function test_no_se_cancela_un_pago_ya_capturado(): void
    {
        $pago = $this->pago();
        $pago->capturar('ref-1');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Solo se cancela un pago pendiente o autorizado');

        $pago->cancelar('expiro');
    }

    public function test_cancelar_emite_pago_cancelado(): void
    {
        $pago = $this->pago();

        $pago->cancelar('compensacion de la saga');

        self::assertSame(EstadoPago::CANCELADO, $pago->estado());
        self::assertSame('pago.cancelado', $pago->sacarEventos()[0]->tipoEvento());
    }

    public function test_un_reembolso_parcial_deja_el_pago_reembolsado_parcial(): void
    {
        $pago = $this->pago(subtotal: 100000, cargoServicio: 0);
        $pago->capturar('ref-1');
        $pago->sacarEventos();

        $pago->aplicarReembolso(Money::deCentavos(40000));

        self::assertSame(EstadoPago::REEMBOLSADO_PARCIAL, $pago->estado());
        self::assertTrue($pago->admiteReembolso(), 'todavia queda saldo por devolver');
    }

    public function test_un_reembolso_total_deja_el_pago_reembolsado(): void
    {
        $pago = $this->pago(subtotal: 100000, cargoServicio: 0);
        $pago->capturar('ref-1');

        $pago->aplicarReembolso(Money::deCentavos(100000));

        self::assertSame(EstadoPago::REEMBOLSADO, $pago->estado());
        self::assertFalse($pago->admiteReembolso());
    }

    public function test_no_se_reembolsa_mas_que_el_total_cobrado(): void
    {
        $pago = $this->pago(subtotal: 100000, cargoServicio: 0);
        $pago->capturar('ref-1');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No se puede reembolsar mas que el total del pago');

        $pago->aplicarReembolso(Money::deCentavos(100001));
    }

    public function test_no_se_reembolsa_un_pago_que_no_se_cobro(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No se puede reembolsar un pago en estado PENDIENTE');

        $this->pago()->aplicarReembolso(Money::deCentavos(100));
    }

    public function test_los_estados_vivos_son_los_que_ocupan_la_reserva(): void
    {
        self::assertTrue(EstadoPago::PENDIENTE->estaVivo());
        self::assertTrue(EstadoPago::AUTORIZADO->estaVivo());
        self::assertTrue(EstadoPago::CAPTURADO->estaVivo());
        self::assertFalse(EstadoPago::FALLIDO->estaVivo());
        self::assertFalse(EstadoPago::CANCELADO->estaVivo());
        self::assertFalse(EstadoPago::REEMBOLSADO->estaVivo());
    }

    private function pago(
        int $subtotal = 50000,
        int $descuento = 0,
        int $cargoServicio = 2500,
        string $idempotencyKey = 'saga-1',
    ): Pago {
        return Pago::crear(
            pagoId: 'pago-1',
            reservaId: 'reserva-1',
            clienteId: 'cliente-1',
            anfitrionId: 'anfitrion-1',
            subtotal: Money::deCentavos($subtotal),
            descuento: Money::deCentavos($descuento),
            cargoServicio: Money::deCentavos($cargoServicio),
            metodo: MetodoPago::QR,
            idempotencyKey: $idempotencyKey,
            sagaId: 'saga-1',
        );
    }
}
