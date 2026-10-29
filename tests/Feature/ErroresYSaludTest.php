<?php

declare(strict_types=1);

namespace Tests\Feature;

use DomainException;
use Illuminate\Support\Facades\Route;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\OperacionNoPermitida;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Tests\TestCase;

final class ErroresYSaludTest extends TestCase
{
    public function test_cada_excepcion_de_dominio_se_traduce_a_su_status_con_codigo_y_mensaje(): void
    {
        $casos = [
            [RecursoNoEncontrado::class, 404, 'no_encontrado'],
            [ConflictoDeEstado::class, 409, 'conflicto'],
            [ReglaDeNegocioViolada::class, 422, 'regla_de_negocio'],
            [OperacionNoPermitida::class, 403, 'operacion_no_permitida'],
            [DomainException::class, 422, 'regla_de_negocio'],
        ];

        foreach ($casos as $i => [$clase, $status, $codigo]) {
            Route::get("api/v1/_falla{$i}", static fn () => throw new $clase('detalle'));

            $this->getJson("api/v1/_falla{$i}")
                ->assertStatus($status)
                ->assertExactJson(['codigo' => $codigo, 'mensaje' => 'detalle']);
        }
    }

    public function test_la_salud_responde_200_sin_autenticacion(): void
    {
        $this->getJson('api/v1/salud')->assertOk()->assertJson(['estado' => 'ok']);
    }
}
