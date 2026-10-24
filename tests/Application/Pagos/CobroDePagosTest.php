<?php

declare(strict_types=1);

namespace Tests\Application\Pagos;

use DomainException;
use PHPUnit\Framework\TestCase;
use Src\Pagos\Application\UseCase\CobrarPago\CobrarPagoCommand;
use Src\Pagos\Application\UseCase\CobrarPago\CobrarPagoHandler;
use Src\Pagos\Application\UseCase\CrearPago\CrearPagoCommand;
use Src\Pagos\Application\UseCase\CrearPago\CrearPagoHandler;
use Src\Pagos\Domain\EstadoPago;
use Src\Pagos\Domain\EstadoTransaccion;
use Src\Pagos\Domain\MetodoPago;
use Src\Pagos\Domain\TipoTransaccion;
use Tests\Doubles\FakePasarelaPago;
use Tests\Doubles\InMemoryEventPublisher;
use Tests\Doubles\InMemoryPagoRepository;
use Tests\Doubles\InMemoryTransaccionPagoRepository;
use Tests\Doubles\SequentialUuidGenerator;
use Tests\Doubles\SyncTransactionManager;

/**
 * CU-PAG-02 y CU-PAG-03: el paso de la saga donde se cobra.
 *
 * Casos de uso con dobles en memoria: sin Laravel, sin base de datos y sin
 * pasarela real.
 */
final class CobroDePagosTest extends TestCase
{
    private InMemoryPagoRepository $pagos;
    private InMemoryTransaccionPagoRepository $transacciones;
    private InMemoryEventPublisher $eventos;
    private SyncTransactionManager $tx;
    private SequentialUuidGenerator $uuid;

    protected function setUp(): void
    {
        $this->pagos = new InMemoryPagoRepository();
        $this->transacciones = new InMemoryTransaccionPagoRepository();
        $this->eventos = new InMemoryEventPublisher();
        $this->tx = new SyncTransactionManager();
        $this->uuid = new SequentialUuidGenerator();
    }

    public function test_crear_pago_lo_deja_pendiente_con_el_desglose_cotizado(): void
    {
        $pago = ($this->crearPago())($this->comandoDeCreacion());

        self::assertSame(EstadoPago::PENDIENTE, $pago->estado());
        self::assertSame('945.00', $pago->total()->aDecimal());
        self::assertSame(1, $this->pagos->cantidad());
        self::assertSame(1, $this->tx->transacciones(), 'una sola transaccion');
    }

    public function test_dos_llamadas_con_la_misma_idempotency_key_no_crean_dos_pagos(): void
    {
        $handler = $this->crearPago();

        $primero = $handler($this->comandoDeCreacion());
        $segundo = $handler($this->comandoDeCreacion());

        // Es la regla que evita el cobro doble cuando la saga reintenta.
        self::assertSame($primero->pagoId(), $segundo->pagoId());
        self::assertSame(1, $this->pagos->cantidad());
    }

    public function test_una_reserva_no_puede_tener_dos_pagos_vivos(): void
    {
        $handler = $this->crearPago();
        $handler($this->comandoDeCreacion(idempotencyKey: 'saga-1'));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('ya tiene un pago vivo');

        $handler($this->comandoDeCreacion(idempotencyKey: 'saga-2'));
    }

    public function test_cobrar_captura_el_pago_y_emite_pago_capturado(): void
    {
        $pago = ($this->crearPago())($this->comandoDeCreacion());
        $pasarela = new FakePasarelaPago();

        $cobrado = ($this->cobrarPago($pasarela))(new CobrarPagoCommand($pago->pagoId()));

        self::assertSame(EstadoPago::CAPTURADO, $cobrado->estado());
        self::assertSame('FAKE', $cobrado->pasarela());
        self::assertTrue($this->eventos->contiene('pago.capturado'));
        self::assertFalse($this->eventos->contiene('pago.fallido'));
    }

    public function test_cobrar_registra_la_transaccion_exitosa_con_la_pasarela(): void
    {
        $pago = ($this->crearPago())($this->comandoDeCreacion());

        ($this->cobrarPago(new FakePasarelaPago()))(new CobrarPagoCommand($pago->pagoId()));

        self::assertSame(1, $this->transacciones->cantidad());
        $transaccion = $this->transacciones->todas()[0];
        self::assertSame(TipoTransaccion::CAPTURA, $transaccion->tipo);
        self::assertSame(EstadoTransaccion::EXITOSA, $transaccion->estado);
        self::assertSame('945.00', $transaccion->monto->aDecimal());
    }

    public function test_le_pasa_a_la_pasarela_la_misma_idempotency_key_del_pago(): void
    {
        $pago = ($this->crearPago())($this->comandoDeCreacion(idempotencyKey: 'saga-abc'));
        $pasarela = new FakePasarelaPago();

        ($this->cobrarPago($pasarela))(new CobrarPagoCommand($pago->pagoId()));

        self::assertSame('saga-abc', $pasarela->llamadas()[0]['idempotencyKey']);
    }

    public function test_si_la_pasarela_rechaza_el_pago_queda_fallido_y_emite_pago_fallido(): void
    {
        $pago = ($this->crearPago())($this->comandoDeCreacion());
        $pasarela = (new FakePasarelaPago())->rechazandoCobros('tarjeta vencida');

        $cobrado = ($this->cobrarPago($pasarela))(new CobrarPagoCommand($pago->pagoId()));

        self::assertSame(EstadoPago::FALLIDO, $cobrado->estado());
        self::assertTrue($this->eventos->contiene('pago.fallido'));
        self::assertSame(
            EstadoTransaccion::FALLIDA,
            $this->transacciones->todas()[0]->estado,
            'el intento fallido queda registrado',
        );
    }

    public function test_cobrar_dos_veces_el_mismo_pago_no_llama_otra_vez_a_la_pasarela(): void
    {
        $pago = ($this->crearPago())($this->comandoDeCreacion());
        $pasarela = new FakePasarelaPago();
        $handler = $this->cobrarPago($pasarela);

        $handler(new CobrarPagoCommand($pago->pagoId()));
        $handler(new CobrarPagoCommand($pago->pagoId()));

        self::assertCount(1, $pasarela->llamadas(), 'no se cobra dos veces');
        self::assertSame(1, $this->eventos->cantidad(), 'ni se publica el evento dos veces');
    }

    public function test_cobrar_un_pago_que_no_existe_falla(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no existe');

        ($this->cobrarPago(new FakePasarelaPago()))(new CobrarPagoCommand('pago-inexistente'));
    }

    public function test_el_cambio_de_estado_y_el_evento_van_en_una_sola_transaccion(): void
    {
        $pago = ($this->crearPago())($this->comandoDeCreacion());
        $transaccionesAntes = $this->tx->transacciones();

        ($this->cobrarPago(new FakePasarelaPago()))(new CobrarPagoCommand($pago->pagoId()));

        // Estado + transaccion con la pasarela + evento: una sola transaccion.
        self::assertSame($transaccionesAntes + 1, $this->tx->transacciones());
    }

    private function crearPago(): CrearPagoHandler
    {
        return new CrearPagoHandler($this->pagos, $this->uuid, $this->tx);
    }

    private function cobrarPago(FakePasarelaPago $pasarela): CobrarPagoHandler
    {
        return new CobrarPagoHandler(
            $this->pagos,
            $this->transacciones,
            $pasarela,
            $this->eventos,
            $this->tx,
            $this->uuid,
        );
    }

    private function comandoDeCreacion(string $idempotencyKey = 'saga-1'): CrearPagoCommand
    {
        return new CrearPagoCommand(
            reservaId: 'reserva-1',
            clienteId: 'cliente-1',
            anfitrionId: 'anfitrion-1',
            subtotalCentavos: 100000,
            cargoServicioCentavos: 4500,
            metodo: MetodoPago::QR,
            idempotencyKey: $idempotencyKey,
            descuentoCentavos: 10000,
            sagaId: 'saga-1',
        );
    }
}
