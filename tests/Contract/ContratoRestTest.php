<?php

declare(strict_types=1);

namespace Tests\Contract;

use Tests\TestCase;

/**
 * La forma de las respuestas REST que consumen MS-02, MS-04 y el frontend:
 * sobre `{data}` y errores `{codigo, mensaje}`, con el dinero como
 * `{centavos, moneda, monto}`.
 */
final class ContratoRestTest extends TestCase
{
    use ProduceEventos;

    public function test_las_respuestas_exitosas_vienen_en_data_con_dinero_estructurado(): void
    {
        $this->eventosProducidos();
        $cliente = $this->como(self::CLIENTE, 'CLIENTE');
        $dinero = ['centavos', 'moneda', 'monto'];

        $pago = $this->postJson('api/v1/pagos', [
            'reserva_id' => '11111111-0000-4000-8000-000000000009', 'anfitrion_id' => self::ANFITRION,
            'subtotal_centavos' => 100000, 'cargo_servicio_centavos' => 5000, 'metodo' => 'QR',
        ], $cliente + ['Idempotency-Key' => 'contrato-1'])
            ->assertCreated()
            ->assertJsonStructure(['data' => [
                'pago_id', 'reserva_id', 'cliente_id', 'anfitrion_id', 'estado', 'idempotency_key',
                'subtotal' => $dinero, 'total' => $dinero, 'monto_anfitrion' => $dinero,
            ]])
            ->assertJsonPath('data.total.centavos', 105000)
            ->json('data.pago_id');

        $this->getJson("api/v1/pagos/{$pago}", $cliente)
            ->assertOk()
            ->assertJsonStructure(['data' => ['pago' => ['pago_id', 'estado'], 'transacciones', 'reembolsos', 'total_reembolsado' => $dinero]]);

        $this->getJson('api/v1/pagos', $cliente)->assertOk()->assertJsonStructure(['data' => [['pago_id', 'estado']]]);
    }

    public function test_los_errores_siempre_traen_codigo_y_mensaje(): void
    {
        $this->eventosProducidos();
        $cliente = $this->como(self::CLIENTE, 'CLIENTE');

        $casos = [
            [404, 'no_encontrado', fn () => $this->getJson('api/v1/pagos/00000000-0000-4000-8000-00000000ffff', $this->como(self::ADMIN, 'ADMIN'))],
            [422, 'validacion', fn () => $this->postJson('api/v1/pagos', ['metodo' => 'QR'], $cliente + ['Idempotency-Key' => 'x'])],
            [403, 'operacion_no_permitida', fn () => $this->postJson('api/v1/reglas-monetizacion', [], $cliente)],
            [401, 'no_autenticado', fn () => $this->getJson('api/v1/pagos')],
        ];

        foreach ($casos as [$status, $codigo, $llamada]) {
            $llamada()->assertStatus($status)->assertExactJsonStructure(['codigo', 'mensaje'])->assertJsonPath('codigo', $codigo);
        }
    }
}
