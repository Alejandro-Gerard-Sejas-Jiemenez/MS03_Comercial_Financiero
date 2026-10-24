<?php

declare(strict_types=1);

namespace Tests\Application\Promociones;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Promociones\Application\UseCase\VencerPromociones\VencerPromocionesCommand;
use Src\Promociones\Application\UseCase\VencerPromociones\VencerPromocionesHandler;
use Src\Promociones\Domain\EstadoPromocion;
use Src\Promociones\Domain\Promocion;
use Src\Promociones\Domain\RolCreador;
use Src\Promociones\Domain\TipoDescuento;
use Src\Promociones\Domain\TipoPromocion;
use Tests\Doubles\FrozenClock;
use Tests\Doubles\InMemoryPromocionRepository;
use Tests\Doubles\SyncTransactionManager;

/**
 * CU-PRO-04 VencerPromociones (JOB). La fecha viene del puerto Clock, nunca de
 * `now()`.
 */
final class VencerPromocionesTest extends TestCase
{
    private InMemoryPromocionRepository $promociones;

    private FrozenClock $clock;

    private SyncTransactionManager $transacciones;

    private VencerPromocionesHandler $handler;

    protected function setUp(): void
    {
        $this->promociones = new InMemoryPromocionRepository();
        $this->clock = new FrozenClock('2026-10-23 12:00:00');
        $this->transacciones = new SyncTransactionManager();
        $this->handler = new VencerPromocionesHandler(
            $this->promociones,
            $this->clock,
            $this->transacciones,
        );
    }

    public function test_solo_vence_las_promociones_cuya_fecha_ya_paso(): void
    {
        $pasada = $this->guardarActiva('promo-pasada', vigenteHasta: '2026-10-20 23:59:59');
        $vigente = $this->guardarActiva('promo-vigente', vigenteHasta: '2026-12-31 23:59:59');
        $cancelada = $this->guardarActiva('promo-cancelada', vigenteHasta: '2026-10-10 23:59:59');
        $cancelada->cancelar();

        $vencidas = ($this->handler)(new VencerPromocionesCommand());

        self::assertSame(['promo-pasada'], $vencidas);
        self::assertSame(EstadoPromocion::VENCIDA, $pasada->estado());
        self::assertSame(EstadoPromocion::ACTIVA, $vigente->estado());
        // Una promocion ya cancelada no se toca.
        self::assertSame(EstadoPromocion::CANCELADA, $cancelada->estado());
        self::assertSame(1, $this->transacciones->transacciones());
    }

    public function test_usa_la_fecha_del_comando_cuando_viene(): void
    {
        $promocion = $this->guardarActiva('promo-1', vigenteHasta: '2026-11-30 23:59:59');

        // Con la fecha del reloj (23 de octubre) todavia no vence.
        self::assertSame([], ($this->handler)(new VencerPromocionesCommand()));
        self::assertSame(EstadoPromocion::ACTIVA, $promocion->estado());

        $vencidas = ($this->handler)(new VencerPromocionesCommand(
            new DateTimeImmutable('2026-12-01 00:00:00'),
        ));

        self::assertSame(['promo-1'], $vencidas);
        self::assertSame(EstadoPromocion::VENCIDA, $promocion->estado());
    }

    public function test_sin_promociones_por_vencer_no_escribe_nada(): void
    {
        $this->guardarActiva('promo-1', vigenteHasta: '2026-12-31 23:59:59');

        self::assertSame([], ($this->handler)(new VencerPromocionesCommand()));
        self::assertSame(1, $this->promociones->guardadas());
    }

    private function guardarActiva(string $promocionId, string $vigenteHasta): Promocion
    {
        $promocion = Promocion::crear(
            promocionId: $promocionId,
            nombre: 'Promocion ' . $promocionId,
            tipo: TipoPromocion::TEMPORADA,
            tipoDescuento: TipoDescuento::PORCENTAJE,
            valor: '10.00',
            vigenteDesde: new DateTimeImmutable('2026-09-01 00:00:00'),
            vigenteHasta: new DateTimeImmutable($vigenteHasta),
            creadaPorRol: RolCreador::ADMIN,
            creadaPor: 'usr-1',
        );
        $promocion->activar();
        $this->promociones->guardar($promocion);

        return $promocion;
    }
}
