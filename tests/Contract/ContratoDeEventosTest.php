<?php

declare(strict_types=1);

namespace Tests\Contract;

use Tests\TestCase;

/**
 * Los eventos que MS-03 publica son un contrato con MS-01, MS-02 y MS-04.
 * `tests/Contract/eventos/*.json` lo describe campo por campo; si un cambio
 * agrega, quita o cambia el tipo de un campo, esta prueba falla y obliga a
 * avisar a los otros equipos antes de publicar.
 *
 * Tipos: uuid, decimal (2 decimales), currency, int, date, datetime, string;
 * con `?` el valor puede ser null.
 */
final class ContratoDeEventosTest extends TestCase
{
    use ProduceEventos;

    public function test_cada_evento_publicado_cumple_su_esquema_campo_por_campo(): void
    {
        $producidos = $this->eventosProducidos();
        $esquemas = glob(__DIR__.'/eventos/*.json') ?: [];
        self::assertNotEmpty($esquemas);

        foreach ($esquemas as $archivo) {
            $tipo = basename($archivo, '.json');
            $esquema = json_decode((string) file_get_contents($archivo), true, 512, JSON_THROW_ON_ERROR);

            self::assertArrayHasKey($tipo, $producidos, "el escenario no produjo '{$tipo}': el esquema quedo huerfano");
            $evento = $producidos[$tipo];
            self::assertSame($esquema['agregado_tipo'], $evento->agregadoTipo(), $tipo);

            $payload = $evento->payload();
            self::assertEqualsCanonicalizing(
                array_keys($esquema['payload']),
                array_keys($payload),
                "{$tipo}: los campos del payload cambiaron",
            );
            foreach ($esquema['payload'] as $campo => $tipoEsperado) {
                self::assertTrue(
                    $this->cumple($payload[$campo], $tipoEsperado),
                    "{$tipo}.{$campo}: se esperaba {$tipoEsperado}, llego ".json_encode($payload[$campo]),
                );
            }
        }
    }

    private function cumple(mixed $valor, string $tipo): bool
    {
        if (str_ends_with($tipo, '?')) {
            if ($valor === null) {
                return true;
            }
            $tipo = substr($tipo, 0, -1);
        }

        return match ($tipo) {
            'uuid' => is_string($valor) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $valor) === 1,
            'decimal' => is_string($valor) && preg_match('/^-?\d+\.\d{2}$/', $valor) === 1,
            'currency' => is_string($valor) && preg_match('/^[A-Z]{3}$/', $valor) === 1,
            'int' => is_int($valor),
            'date' => is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1,
            'datetime' => is_string($valor) && strtotime($valor) !== false && str_contains($valor, 'T'),
            'string' => is_string($valor) && $valor !== '',
            default => throw new \LogicException("tipo de contrato desconocido: {$tipo}"),
        };
    }
}
