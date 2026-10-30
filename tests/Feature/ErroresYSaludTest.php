<?php

declare(strict_types=1);

namespace Tests\Feature;

use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Src\Shared\Domain\Exception\ConflictoDeEstado;
use Src\Shared\Domain\Exception\OperacionNoPermitida;
use Src\Shared\Domain\Exception\RecursoNoEncontrado;
use Src\Shared\Domain\Exception\ReglaDeNegocioViolada;
use Src\Shared\Presentation\Http\ManejadorDeExcepciones;
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

    public function test_un_fallo_de_clave_foranea_o_unica_de_la_base_no_es_un_500(): void
    {
        $sql = fn (string $estado): QueryException => new QueryException('pgsql', 'insert', [], $this->pdo($estado));

        $fk = ManejadorDeExcepciones::aRespuesta($sql('23503'));
        $unica = ManejadorDeExcepciones::aRespuesta($sql('23505'));

        self::assertSame([422, 'referencia_inexistente'], [$fk->getStatusCode(), $fk->getData(true)['codigo']]);
        self::assertSame([409, 'conflicto'], [$unica->getStatusCode(), $unica->getData(true)['codigo']]);
    }

    private function pdo(string $sqlState): \PDOException
    {
        $e = new \PDOException('SQLSTATE['.$sqlState.']');
        (new \ReflectionProperty(\Exception::class, 'code'))->setValue($e, $sqlState);

        return $e;
    }

    public function test_la_salud_responde_200_sin_autenticacion(): void
    {
        $this->getJson('api/v1/salud')->assertOk()->assertJson(['estado' => 'ok']);
    }
}
