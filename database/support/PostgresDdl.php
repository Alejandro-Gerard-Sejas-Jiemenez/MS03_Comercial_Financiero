<?php

declare(strict_types=1);

namespace Database\Support;

use Illuminate\Support\Facades\DB;

/**
 * Lo que el schema builder de Laravel no expresa y el DDL de MS-03 si necesita:
 * restricciones CHECK, indices unicos parciales, defaults de UUID y los
 * triggers de las tablas append-only.
 *
 * Todo esto es especifico de PostgreSQL; en otro driver no hace nada, para que
 * las pruebas que no tocan la base no se rompan.
 */
final class PostgresDdl
{
    public static function soportado(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    public static function check(string $tabla, string $nombre, string $expresion): void
    {
        self::ejecutar("ALTER TABLE {$tabla} ADD CONSTRAINT {$nombre} CHECK ({$expresion})");
    }

    /** Equivale al `DEFAULT gen_random_uuid()` del DDL. */
    public static function defaultUuid(string $tabla, string $columna): void
    {
        self::ejecutar("ALTER TABLE {$tabla} ALTER COLUMN {$columna} SET DEFAULT gen_random_uuid()");
    }

    /** Indice unico parcial: `CREATE UNIQUE INDEX ... WHERE <condicion>`. */
    public static function uniqueParcial(string $nombre, string $tabla, string $columnas, string $donde): void
    {
        self::ejecutar("CREATE UNIQUE INDEX {$nombre} ON {$tabla} ({$columnas}) WHERE {$donde}");
    }

    /** Funcion compartida por los triggers de inmutabilidad. */
    public static function funcionProhibirModificacion(): void
    {
        self::ejecutar(<<<'SQL'
            CREATE OR REPLACE FUNCTION prohibir_modificacion() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Tabla % es append-only: no se permite % (corregir con un asiento de reversa)',
                    TG_TABLE_NAME, TG_OP;
            END $$;
            SQL);
    }

    /** Deja la tabla en solo-insercion: UPDATE y DELETE lanzan excepcion. */
    public static function appendOnly(string $tabla, string $trigger): void
    {
        self::ejecutar(
            "CREATE TRIGGER {$trigger} BEFORE UPDATE OR DELETE ON {$tabla} "
            . 'FOR EACH ROW EXECUTE FUNCTION prohibir_modificacion()',
        );
    }

    public static function ejecutar(string $sql): void
    {
        if (! self::soportado()) {
            return;
        }

        DB::unprepared($sql);
    }
}
