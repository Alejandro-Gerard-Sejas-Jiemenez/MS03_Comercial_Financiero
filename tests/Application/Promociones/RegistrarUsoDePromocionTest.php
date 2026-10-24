<?php

declare(strict_types=1);

namespace Tests\Application\Promociones;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use Src\Monetizacion\Domain\FinanciamientoDescuento;
use Src\Promociones\Application\UseCase\RegistrarUsoDePromocion\RegistrarUsoDePromocionCommand;
use Src\Promociones\Application\UseCase\RegistrarUsoDePromocion\RegistrarUsoDePromocionHandler;
use Src\Promociones\Domain\Promocion;
use Src\Promociones\Domain\RolCreador;
use Src\Promociones\Domain\TipoDescuento;
use Src\Promociones\Domain\TipoPromocion;
use Src\Shared\Domain\Money;
use Tests\Doubles\FrozenClock;
use Tests\Doubles\InMemoryEventPublisher;
use Tests\Doubles\InMemoryPromocionRepository;
use Tests\Doubles\SyncTransactionManager;

/**
 * CU-PRO-03 RegistrarUsoDePromocion (disparador INT: evento `PagoCapturado`).
 */
final class RegistrarUsoDePromocionTest extends TestCase
{
    private InMemoryPromocionRepository $promociones;

    private InMemoryEventPublisher $eventos;

    private SyncTransactionManager $transacciones;

    private RegistrarUsoDePromocionHandler $handler;

    protected function setUp(): void
    {
        $this->promociones = new InMemoryPromocionRepository();
        $this->eventos = new InMemoryEventPublisher();
        $this->transacciones = new SyncTransactionManager();
        $this->handler = new RegistrarUsoDePromocionHandler(
            $this->promociones,
            $this->eventos,
            $this->transacciones,
            new FrozenClock('2026-10-23 12:00:00'),
        );
    }

    public function test_incrementa_usos_actuales_y_emite_promocion_aplicada(): void
    {
        $this->guardarActiva('promo-1', maxUsos: 5);

        $promocion = ($this->handler)(new RegistrarUsoDePromocionCommand(
            promocionId: 'promo-1',
            descuentoCentavos: 2550,
            reservaId: 'res-1',
            pagoId: 'pag-1',
            sagaId: 'saga-1',
        ));

        self::assertSame(1, $promocion->usosActuales());
        // Una escritura al preparar el escenario y otra del caso de uso.
        self::assertSame(2, $this->promociones->guardadas());
        self::assertTrue($this->eventos->contiene('promocion.aplicada'));
        self::assertSame(1, $this->eventos->cantidad());

        $payload = $this->eventos->primero()?->payload() ?? [];
        self::assertSame('promo-1', $payload['promocion_id']);
        self::assertSame('25.50', $payload['descuento']);
        self::assertSame('res-1', $payload['reserva_id']);
        self::assertSame('saga-1', $this->eventos->primero()?->sagaId());

        // El guardado y la publicacion van en la misma transaccion (Outbox).
        self::assertSame(1, $this->transacciones->transacciones());
        self::assertSame(0, $this->transacciones->revertidas());
    }

    public function test_al_llegar_al_tope_de_usos_falla_y_no_publica_nada(): void
    {
        $promocion = $this->guardarActiva('promo-1', maxUsos: 1);
        $promocion->registrarUso(new DateTimeImmutable('2026-10-22 10:00:00'), Money::deCentavos(1000));
        $promocion->sacarEventos();

        try {
            ($this->handler)(new RegistrarUsoDePromocionCommand(
                promocionId: 'promo-1',
                descuentoCentavos: 1000,
            ));
            self::fail('Se esperaba una excepcion de dominio por el tope de usos');
        } catch (DomainException $e) {
            self::assertStringContainsString('tope de usos', $e->getMessage());
        }

        self::assertSame(1, $promocion->usosActuales());
        self::assertSame(0, $this->eventos->cantidad());
        self::assertSame(1, $this->transacciones->revertidas());
    }

    public function test_falla_si_la_promocion_no_existe(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no existe');

        ($this->handler)(new RegistrarUsoDePromocionCommand(
            promocionId: 'promo-9',
            descuentoCentavos: 1000,
        ));
    }

    public function test_la_fecha_de_aplicacion_sale_del_reloj(): void
    {
        $this->guardarActiva('promo-1');

        ($this->handler)(new RegistrarUsoDePromocionCommand(
            promocionId: 'promo-1',
            descuentoCentavos: 1000,
        ));

        $payload = $this->eventos->primero()?->payload() ?? [];
        self::assertStringStartsWith('2026-10-23T12:00:00', (string) $payload['aplicada_en']);
    }

    private function guardarActiva(string $promocionId, ?int $maxUsos = null): Promocion
    {
        $promocion = Promocion::crear(
            promocionId: $promocionId,
            nombre: 'Promocion ' . $promocionId,
            tipo: TipoPromocion::TEMPORADA,
            tipoDescuento: TipoDescuento::PORCENTAJE,
            valor: '10.00',
            vigenteDesde: new DateTimeImmutable('2026-10-01 00:00:00'),
            vigenteHasta: new DateTimeImmutable('2026-12-31 23:59:59'),
            creadaPorRol: RolCreador::ANFITRION,
            creadaPor: 'usr-1',
            anfitrionId: 'anf-1',
            financiadaPor: FinanciamientoDescuento::ANFITRION,
            maxUsos: $maxUsos,
        );
        $promocion->activar();
        $this->promociones->guardar($promocion);

        return $promocion;
    }
}
