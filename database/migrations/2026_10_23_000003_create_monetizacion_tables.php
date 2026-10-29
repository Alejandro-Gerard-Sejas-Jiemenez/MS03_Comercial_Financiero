<?php

declare(strict_types=1);

use Database\Support\PostgresDdl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paquete Monetizacion (F16): reglas de comision y cargos con vigencia.
 * La regla guarda el porcentaje o el monto fijo; la comision calculada guarda
 * despues su propio snapshot, para que cambiar la regla no altere el historico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regla_monetizacion', function (Blueprint $table): void {
            $table->uuid('regla_id')->primary();
            $table->string('nombre', 100);
            $table->string('concepto', 25);                 // COMISION_ANFITRION | CARGO_SERVICIO_CLIENTE
            $table->string('tipo_calculo', 12);             // PORCENTAJE | MONTO_FIJO
            $table->decimal('porcentaje', 5, 2)->nullable();
            $table->decimal('monto_fijo', 12, 2)->nullable();
            $table->char('moneda', 3)->default('BOB');
            $table->string('categoria_alojamiento', 30)->nullable();   // NULL = todas
            $table->string('nivel_anfitrion', 20)->nullable();         // NULL = todos
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();                 // NULL = indefinida
            $table->string('estado', 10)->default('ACTIVA');
            $table->uuid('creado_por');                                // ref MS-01 (administrador)
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(
                ['concepto', 'categoria_alojamiento', 'vigente_desde', 'vigente_hasta'],
                'idx_regla_vigencia',
            );
        });

        PostgresDdl::defaultUuid('regla_monetizacion', 'regla_id');
        PostgresDdl::check(
            'regla_monetizacion',
            'chk_regla_concepto',
            "concepto IN ('COMISION_ANFITRION','CARGO_SERVICIO_CLIENTE')",
        );
        PostgresDdl::check(
            'regla_monetizacion',
            'chk_regla_tipo_calculo',
            "tipo_calculo IN ('PORCENTAJE','MONTO_FIJO')",
        );
        PostgresDdl::check('regla_monetizacion', 'chk_regla_porcentaje', 'porcentaje BETWEEN 0 AND 100');
        PostgresDdl::check('regla_monetizacion', 'chk_regla_monto_fijo', 'monto_fijo >= 0');
        PostgresDdl::check('regla_monetizacion', 'chk_regla_moneda', "moneda IN ('BOB','USD')");
        PostgresDdl::check('regla_monetizacion', 'chk_regla_estado', "estado IN ('ACTIVA','INACTIVA')");
        // El tipo de calculo obliga a llenar su columna: una regla PORCENTAJE sin
        // porcentaje no puede existir.
        PostgresDdl::check(
            'regla_monetizacion',
            'chk_regla_calculo_coherente',
            "(tipo_calculo = 'PORCENTAJE' AND porcentaje IS NOT NULL)"
            ." OR (tipo_calculo = 'MONTO_FIJO' AND monto_fijo IS NOT NULL)",
        );
        PostgresDdl::check(
            'regla_monetizacion',
            'chk_regla_vigencia',
            'vigente_hasta IS NULL OR vigente_hasta >= vigente_desde',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('regla_monetizacion');
    }
};
