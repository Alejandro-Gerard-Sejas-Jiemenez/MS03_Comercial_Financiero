<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Src\Pagos\Domain\MetodoPago;
use Src\Pagos\Domain\Pago;
use Src\Pagos\Infrastructure\Persistence\EloquentPagoRepository;
use Src\Shared\Domain\Money;
use Tests\TestCase;
use Throwable;

/**
 * Las reglas que el documento dice que "la base garantiza", probadas contra un
 * PostgreSQL real. Nivel 1 de proteccion: el error ni se puede escribir.
 *
 * Se omite sola si no hay PostgreSQL a mano, asi que la suite completa sigue
 * corriendo en una maquina sin Docker:
 *
 *     docker compose up -d
 *     php artisan migrate
 *     php vendor/bin/phpunit --testsuite=Integration
 */
final class RestriccionesDeLaBaseTest extends TestCase
{
    private string $anfitrionId = '11111111-1111-4111-8111-111111111111';

    private string $reservaId = '22222222-2222-4222-8222-222222222222';

    private string $clienteId = '33333333-3333-4333-8333-333333333333';

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'pgsql') {
            self::markTestSkipped('Las restricciones son de PostgreSQL; la conexion activa no lo es.');
        }

        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            self::markTestSkipped('No hay PostgreSQL disponible: '.$e->getMessage());
        }

        if (! DB::getSchemaBuilder()->hasTable('pago')) {
            self::markTestSkipped('Falta correr las migraciones (php artisan migrate).');
        }

        DB::beginTransaction();
        $this->sembrarReferencias();
    }

    protected function tearDown(): void
    {
        // Cada prueba corre dentro de una transaccion que se revierte: la base
        // queda igual que antes.
        DB::rollBack();

        parent::tearDown();
    }

    public function test_rechaza_un_pago_cuyo_total_no_cuadra_con_el_desglose(): void
    {
        $this->expectException(QueryException::class);

        // 1000 - 100 + 45 = 945, no 999.
        $this->insertarPago(montoTotal: '999.00');
    }

    public function test_acepta_un_pago_cuyo_total_cuadra(): void
    {
        $pagoId = $this->insertarPago();

        self::assertSame(
            '945.00',
            DB::table('pago')->where('pago_id', $pagoId)->value('monto_total'),
        );
    }

    public function test_no_admite_dos_pagos_vivos_de_la_misma_reserva(): void
    {
        $this->insertarPago(idempotencyKey: 'saga-a');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/uq_pago_vivo_por_reserva/');

        $this->insertarPago(idempotencyKey: 'saga-b');
    }

    public function test_un_pago_cancelado_deja_libre_la_reserva(): void
    {
        $primero = $this->insertarPago(idempotencyKey: 'saga-a');
        DB::table('pago')->where('pago_id', $primero)->update(['estado' => 'CANCELADO']);

        $segundo = $this->insertarPago(idempotencyKey: 'saga-b');

        self::assertNotSame($primero, $segundo);
        self::assertSame(2, DB::table('pago')->where('reserva_id', $this->reservaId)->count());
    }

    public function test_la_idempotency_key_no_se_puede_repetir(): void
    {
        $this->insertarPago(idempotencyKey: 'saga-unica');
        DB::table('pago')->update(['estado' => 'CANCELADO']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/idempotency_key/');

        $this->insertarPago(idempotencyKey: 'saga-unica', reservaId: $this->reservaId);
    }

    public function test_un_anfitrion_solo_puede_tener_un_acuerdo_activo(): void
    {
        $this->insertarAcuerdo('ACTIVO');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/uq_acuerdo_activo_por_anfitrion/');

        $this->insertarAcuerdo('ACTIVO');
    }

    public function test_un_anfitrion_puede_tener_varios_acuerdos_finalizados(): void
    {
        $this->insertarAcuerdo('FINALIZADO');
        $this->insertarAcuerdo('FINALIZADO');

        self::assertSame(
            2,
            DB::table('acuerdo_comercial')->where('anfitrion_id', $this->anfitrionId)->count(),
        );
    }

    public function test_la_liquidacion_exige_que_el_neto_cuadre(): void
    {
        $this->expectException(QueryException::class);

        // 1200 - 180 - 0 = 1020, no 999.
        $this->insertarLiquidacion(totalNeto: '999.00');
    }

    public function test_el_libro_contable_no_admite_update(): void
    {
        $this->insertarMovimiento();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/append-only/');

        DB::table('movimiento_contable')->update(['monto' => '1.00']);
    }

    public function test_el_libro_contable_no_admite_delete(): void
    {
        $this->insertarMovimiento();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/append-only/');

        DB::table('movimiento_contable')->delete();
    }

    public function test_las_transacciones_con_la_pasarela_no_se_pueden_modificar(): void
    {
        $pagoId = $this->insertarPago();
        DB::table('transaccion_pago')->insert([
            'transaccion_id' => DB::raw('gen_random_uuid()'),
            'pago_id' => $pagoId,
            'tipo' => 'CAPTURA',
            'monto' => '945.00',
            'estado' => 'EXITOSA',
            'referencia_pasarela' => 'ref-integracion-1',
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/append-only/');

        DB::table('transaccion_pago')->update(['estado' => 'FALLIDA']);
    }

    public function test_las_noches_son_una_columna_generada(): void
    {
        self::assertSame(
            4,
            (int) DB::table('ref_reserva')->where('reserva_id', $this->reservaId)->value('noches'),
            'check-in 2026-11-01 y check-out 2026-11-05 son 4 noches',
        );
    }

    public function test_la_diferencia_de_una_conciliacion_es_una_columna_generada(): void
    {
        DB::table('conciliacion')->insert([
            'conciliacion_id' => DB::raw('gen_random_uuid()'),
            'pasarela' => 'FAKE',
            'fecha_corte' => '2026-11-19',
            'total_sistema' => '1000.00',
            'total_pasarela' => '990.00',
            'estado' => 'CON_DIFERENCIAS',
        ]);

        self::assertSame(
            '-10.00',
            DB::table('conciliacion')->where('pasarela', 'FAKE')->value('diferencia'),
        );
    }

    public function test_guardar_un_pago_capturado_estampa_pagado_en_una_sola_vez(): void
    {
        $repositorio = new EloquentPagoRepository;
        $pago = Pago::crear(
            pagoId: '55555555-5555-4555-8555-555555555555',
            reservaId: $this->reservaId,
            clienteId: $this->clienteId,
            anfitrionId: $this->anfitrionId,
            subtotal: Money::deCentavos(100000),
            descuento: Money::deCentavos(0),
            cargoServicio: Money::deCentavos(5000),
            metodo: MetodoPago::QR,
            idempotencyKey: 'integracion-pagado-en',
        );
        $repositorio->guardar($pago);
        self::assertNull(DB::table('pago')->where('pago_id', $pago->pagoId())->value('pagado_en'));

        $pago->capturar('ref-1');
        $repositorio->guardar($pago);
        $primera = DB::table('pago')->where('pago_id', $pago->pagoId())->value('pagado_en');
        self::assertNotNull($primera, 'al capturar se estampa la fecha de cobro');

        $pago->aplicarReembolso(Money::deCentavos(1000));
        $repositorio->guardar($pago);
        self::assertSame($primera, DB::table('pago')->where('pago_id', $pago->pagoId())->value('pagado_en'), 'guardados posteriores no la pisan');
    }

    public function test_un_porcentaje_de_comision_mayor_a_cien_se_rechaza(): void
    {
        $this->expectException(QueryException::class);

        DB::table('regla_monetizacion')->insert([
            'regla_id' => DB::raw('gen_random_uuid()'),
            'nombre' => 'Regla imposible',
            'concepto' => 'COMISION_ANFITRION',
            'tipo_calculo' => 'PORCENTAJE',
            'porcentaje' => '101.00',
            'moneda' => 'BOB',
            'vigente_desde' => '2026-01-01',
            'estado' => 'ACTIVA',
            'creado_por' => $this->anfitrionId,
        ]);
    }

    public function test_una_regla_por_porcentaje_sin_porcentaje_se_rechaza(): void
    {
        $this->expectException(QueryException::class);

        DB::table('regla_monetizacion')->insert([
            'regla_id' => DB::raw('gen_random_uuid()'),
            'nombre' => 'Regla incompleta',
            'concepto' => 'COMISION_ANFITRION',
            'tipo_calculo' => 'PORCENTAJE',
            'porcentaje' => null,
            'moneda' => 'BOB',
            'vigente_desde' => '2026-01-01',
            'estado' => 'ACTIVA',
            'creado_por' => $this->anfitrionId,
        ]);
    }

    private function sembrarReferencias(): void
    {
        DB::table('ref_anfitrion')->insert([
            'anfitrion_id' => $this->anfitrionId,
            'nombre_completo' => 'Anfitrion de integracion',
            'estado_verificacion' => 'VERIFICADO',
        ]);

        DB::table('ref_reserva')->insert([
            'reserva_id' => $this->reservaId,
            'cliente_id' => $this->clienteId,
            'anfitrion_id' => $this->anfitrionId,
            'alojamiento_id' => '44444444-4444-4444-8444-444444444444',
            'categoria_alojamiento' => 'DEPARTAMENTO',
            'fecha_checkin' => '2026-11-01',
            'fecha_checkout' => '2026-11-05',
            'estado' => 'COMPLETADA',
        ]);
    }

    private function insertarPago(
        string $montoTotal = '945.00',
        string $idempotencyKey = 'saga-integracion',
        ?string $reservaId = null,
    ): string {
        $pagoId = (string) DB::selectOne('SELECT gen_random_uuid() AS id')->id;

        DB::table('pago')->insert([
            'pago_id' => $pagoId,
            'reserva_id' => $reservaId ?? $this->reservaId,
            'cliente_id' => $this->clienteId,
            'anfitrion_id' => $this->anfitrionId,
            'subtotal_alojamiento' => '1000.00',
            'descuento' => '100.00',
            'cargo_servicio' => '45.00',
            'monto_total' => $montoTotal,
            'moneda' => 'BOB',
            'metodo' => 'QR',
            'estado' => 'PENDIENTE',
            'idempotency_key' => $idempotencyKey,
        ]);

        return $pagoId;
    }

    private function insertarAcuerdo(string $estado): void
    {
        DB::table('acuerdo_comercial')->insert([
            'acuerdo_id' => DB::raw('gen_random_uuid()'),
            'anfitrion_id' => $this->anfitrionId,
            'estado' => $estado,
            'fecha_inicio' => '2026-01-01',
            'dias_liquidacion' => 7,
        ]);
    }

    private function insertarLiquidacion(string $totalNeto): void
    {
        DB::table('liquidacion')->insert([
            'liquidacion_id' => DB::raw('gen_random_uuid()'),
            'anfitrion_id' => $this->anfitrionId,
            'periodo_desde' => '2026-10-01',
            'periodo_hasta' => '2026-10-31',
            'total_bruto' => '1200.00',
            'total_comisiones' => '180.00',
            'total_reembolsos' => '0.00',
            'total_neto' => $totalNeto,
            'moneda' => 'BOB',
            'estado' => 'GENERADA',
        ]);
    }

    private function insertarMovimiento(): void
    {
        DB::table('movimiento_contable')->insert([
            'asiento_id' => DB::raw('gen_random_uuid()'),
            'cuenta' => 'PASARELA',
            'tipo' => 'DEBITO',
            'monto' => '945.00',
            'moneda' => 'BOB',
            'origen_tipo' => 'PAGO',
            'origen_id' => $this->reservaId,
            'anfitrion_id' => $this->anfitrionId,
        ]);
    }
}
