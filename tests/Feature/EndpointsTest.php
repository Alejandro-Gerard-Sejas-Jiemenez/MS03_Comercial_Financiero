<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Recorre los endpoints reales (ruta -> middleware -> controlador -> caso de
 * uso) con repositorios en memoria. Un flujo representativo por paquete.
 */
final class EndpointsTest extends TestCase
{
    use ConDobles;
    use EmiteTokens;

    private const ADMIN = 'aaaaaaaa-0000-4000-8000-000000000001';

    private const ANFITRION = 'bbbbbbbb-0000-4000-8000-000000000002';

    private const OTRO = 'cccccccc-0000-4000-8000-000000000003';

    private const CLIENTE = 'dddddddd-0000-4000-8000-000000000004';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararKeycloakFalso();
        $this->conDobles();
        (require base_path('database/migrations/2026_10_29_000011_create_idempotencia_http_table.php'))->up();
    }

    public function test_promociones_un_anfitrion_crea_activa_y_el_cliente_obtiene_el_descuento_del_cupon(): void
    {
        $id = $this->postJson('api/v1/promociones', [
            'nombre' => 'Verano', 'tipo' => 'CUPON', 'tipo_descuento' => 'PORCENTAJE', 'valor' => 10,
            'codigo' => 'VERANO', 'vigente_desde' => '2026-10-01', 'vigente_hasta' => '2026-12-31',
        ], $this->como(self::ANFITRION, 'ANFITRION'))
            ->assertCreated()
            ->assertJsonPath('data.estado', 'BORRADOR')
            ->assertJsonPath('data.anfitrion_id', self::ANFITRION)
            ->json('data.promocion_id');

        $this->postJson("api/v1/promociones/{$id}/activacion", [], $this->como(self::ANFITRION, 'ANFITRION'))
            ->assertOk()->assertJsonPath('data.estado', 'ACTIVA');

        $this->postJson('api/v1/promociones/descuento', [
            'subtotal_centavos' => 100000, 'noches' => 2, 'codigo_cupon' => 'VERANO', 'anfitrion_id' => self::ANFITRION,
        ], $this->como(self::CLIENTE, 'CLIENTE'))
            ->assertOk()->assertJsonPath('data.descuento_centavos', 10000);

        // Otro anfitrion no puede tocar una promocion que no es suya.
        $this->postJson("api/v1/promociones/{$id}/pausa", [], $this->como(self::OTRO, 'ANFITRION'))
            ->assertStatus(403);
    }

    public function test_monetizacion_y_acuerdos_los_administra_el_admin_y_el_desglose_usa_la_regla(): void
    {
        $regla = [
            'nombre' => 'Comision base', 'concepto' => 'COMISION_ANFITRION', 'tipo_calculo' => 'PORCENTAJE',
            'porcentaje' => 10, 'vigente_desde' => '2026-01-01',
        ];
        $this->postJson('api/v1/reglas-monetizacion', $regla, $this->como(self::ANFITRION, 'ANFITRION'))
            ->assertStatus(403);
        $this->postJson('api/v1/reglas-monetizacion', $regla, $this->como(self::ADMIN, 'ADMIN'))
            ->assertCreated();

        $this->postJson('api/v1/desglose-comercial', ['subtotal_centavos' => 100000], $this->como(self::CLIENTE, 'CLIENTE'))
            ->assertOk()
            ->assertJsonPath('data.monto_comision.centavos', 10000);

        $acuerdoId = $this->postJson('api/v1/acuerdos', [
            'anfitrion_id' => self::ANFITRION, 'fecha_inicio' => '2026-10-01',
        ], $this->como(self::ADMIN, 'ADMIN'))->assertCreated()->json('data.acuerdo_id');

        $this->postJson("api/v1/acuerdos/{$acuerdoId}/estado", ['estado' => 'ACTIVO'], $this->como(self::ADMIN, 'ADMIN'))
            ->assertOk()->assertJsonPath('data.estado', 'ACTIVO');
        $this->getJson("api/v1/acuerdos/{$acuerdoId}", $this->como(self::OTRO, 'ANFITRION'))->assertStatus(403);
    }

    public function test_pagos_se_crea_con_idempotency_key_se_cobra_y_solo_lo_ven_sus_partes(): void
    {
        $cuerpo = [
            'reserva_id' => '11111111-0000-4000-8000-000000000001', 'anfitrion_id' => self::ANFITRION,
            'subtotal_centavos' => 100000, 'cargo_servicio_centavos' => 5000, 'metodo' => 'QR',
        ];
        $cliente = $this->como(self::CLIENTE, 'CLIENTE');

        $this->postJson('api/v1/pagos', $cuerpo, $cliente)->assertStatus(422);   // falta Idempotency-Key

        $h = $cliente + ['Idempotency-Key' => 'reserva-1'];
        $id = $this->postJson('api/v1/pagos', $cuerpo, $h)
            ->assertCreated()->assertJsonPath('data.estado', 'PENDIENTE')->json('data.pago_id');
        $this->postJson('api/v1/pagos', $cuerpo, $h)
            ->assertCreated()->assertJsonPath('data.pago_id', $id)->assertHeader('Idempotency-Replayed', 'true');

        $this->postJson("api/v1/pagos/{$id}/cobro", [], $cliente + ['Idempotency-Key' => 'cobro-1'])
            ->assertOk()->assertJsonPath('data.estado', 'CAPTURADO');

        $this->getJson("api/v1/pagos/{$id}", $this->como(self::ANFITRION, 'ANFITRION'))->assertOk();
        $this->getJson("api/v1/pagos/{$id}", $this->como(self::OTRO, 'CLIENTE'))->assertStatus(403);
        $this->getJson('api/v1/pagos/00000000-0000-4000-8000-00000000ffff', $this->como(self::ADMIN, 'ADMIN'))
            ->assertStatus(404);
    }

    public function test_todas_las_rutas_de_negocio_exigen_token_salvo_salud_y_el_webhook(): void
    {
        $revisadas = 0;
        foreach (Route::getRoutes() as $ruta) {
            if (! str_starts_with($ruta->uri(), 'api/v1/') || in_array($ruta->uri(), ['api/v1/salud', 'api/v1/pagos/notificaciones-pasarela'], true)) {
                continue;
            }
            $uri = preg_replace('/\{[^}]+\}/', '00000000-0000-4000-8000-000000000001', $ruta->uri());
            $metodo = strtolower($ruta->methods()[0]);

            $this->json($metodo, $uri)->assertStatus(401);
            $revisadas++;
        }

        self::assertGreaterThan(40, $revisadas);
    }

    public function test_el_webhook_de_la_pasarela_se_autentica_con_secreto_compartido(): void
    {
        config(['comercial.pasarela.webhook_secreto' => 's3creto']);
        $cuerpo = ['pago_id' => '11111111-0000-4000-8000-000000000001', 'referencia_pasarela' => 'R1', 'exitosa' => true];

        $this->postJson('api/v1/pagos/notificaciones-pasarela', $cuerpo)->assertStatus(403);
        $this->postJson('api/v1/pagos/notificaciones-pasarela', $cuerpo, ['X-Webhook-Secret' => 'otro'])->assertStatus(403);
        // Con el secreto correcto pasa la autenticacion; el pago no existe, asi que 404 y no 403.
        $this->postJson('api/v1/pagos/notificaciones-pasarela', $cuerpo, ['X-Webhook-Secret' => 's3creto'])->assertStatus(404);
    }
}
