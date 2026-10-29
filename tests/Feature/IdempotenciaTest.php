<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class IdempotenciaTest extends TestCase
{
    private int $ejecuciones = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // La tabla es de Postgres en produccion; aqui se crea con la misma migracion.
        (require base_path('database/migrations/2026_10_29_000011_create_idempotencia_http_table.php'))->up();

        Route::middleware('idempotente:exigir')->post('api/v1/_cobro', function () {
            $this->ejecuciones++;

            return response()->json(['n' => $this->ejecuciones], 201);
        });
    }

    public function test_misma_clave_y_mismo_cuerpo_devuelve_la_primera_respuesta_sin_ejecutar_otra_vez(): void
    {
        $h = ['Idempotency-Key' => 'k-1'];

        $this->postJson('api/v1/_cobro', ['monto' => 100], $h)->assertCreated()->assertJson(['n' => 1]);
        $this->postJson('api/v1/_cobro', ['monto' => 100], $h)
            ->assertCreated()
            ->assertJson(['n' => 1])
            ->assertHeader('Idempotency-Replayed', 'true');

        self::assertSame(1, $this->ejecuciones);
    }

    public function test_misma_clave_con_otro_cuerpo_o_sin_clave_da_422(): void
    {
        $this->postJson('api/v1/_cobro', ['monto' => 100], ['Idempotency-Key' => 'k-2'])->assertCreated();

        $this->postJson('api/v1/_cobro', ['monto' => 999], ['Idempotency-Key' => 'k-2'])
            ->assertStatus(422)
            ->assertJsonPath('codigo', 'regla_de_negocio');
        $this->postJson('api/v1/_cobro', ['monto' => 100])->assertStatus(422);
    }
}
