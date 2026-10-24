<?php

declare(strict_types=1);

use Database\Support\PostgresDdl;
use Illuminate\Database\Migrations\Migration;

/**
 * Esquema `comercial` y la funcion que protege las tablas append-only.
 *
 * Todas las tablas de MS-03 viven en `comercial`; la conexion las resuelve con
 * `search_path = comercial,public` (ver config/database.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        PostgresDdl::ejecutar('CREATE SCHEMA IF NOT EXISTS comercial');
        PostgresDdl::funcionProhibirModificacion();
    }

    public function down(): void
    {
        PostgresDdl::ejecutar('DROP FUNCTION IF EXISTS prohibir_modificacion() CASCADE');
        PostgresDdl::ejecutar('DROP SCHEMA IF EXISTS comercial CASCADE');
    }
};
