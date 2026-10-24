<?php

declare(strict_types=1);

use Database\Support\PostgresDdl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paquete Pagos (F05), conciliacion: compara lo que registro MS-03 contra el
 * reporte de la pasarela en una fecha de corte. `diferencia` es una columna
 * generada, asi nadie puede guardar una diferencia que no cuadre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conciliacion', function (Blueprint $table): void {
            $table->uuid('conciliacion_id')->primary();
            $table->string('pasarela', 30);
            $table->date('fecha_corte');
            $table->decimal('total_sistema', 14, 2);
            $table->decimal('total_pasarela', 14, 2);
            $table->decimal('diferencia', 14, 2)->storedAs('total_pasarela - total_sistema');
            // El DDL de `context/ms03_comercial_financiero.sql` declara
            // VARCHAR(12), pero su propio CHECK admite 'CON_DIFERENCIAS', que
            // tiene 15 caracteres: cualquier conciliacion con diferencias
            // fallaba al guardarse. Se corrige a 20.
            $table->string('estado', 20)->default('ABIERTA');
            $table->uuid('ejecutada_por')->nullable();       // ref MS-01
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['pasarela', 'fecha_corte'], 'uq_conciliacion_corte');
        });

        PostgresDdl::defaultUuid('conciliacion', 'conciliacion_id');
        PostgresDdl::check(
            'conciliacion',
            'chk_conciliacion_estado',
            "estado IN ('ABIERTA','CONCILIADA','CON_DIFERENCIAS')",
        );

        Schema::create('conciliacion_item', function (Blueprint $table): void {
            $table->uuid('item_id')->primary();
            $table->uuid('conciliacion_id');
            $table->uuid('transaccion_id')->nullable();      // NULL = la pasarela reporto algo que no tenemos
            $table->string('referencia_pasarela', 100);
            $table->decimal('monto_pasarela', 12, 2);
            $table->string('resultado', 20);                 // COINCIDE | DIFERENCIA_MONTO | NO_REGISTRADA
            $table->text('observacion')->nullable();

            $table->foreign('conciliacion_id')
                ->references('conciliacion_id')->on('conciliacion')->cascadeOnDelete();
            $table->foreign('transaccion_id')->references('transaccion_id')->on('transaccion_pago');
        });

        PostgresDdl::defaultUuid('conciliacion_item', 'item_id');
        PostgresDdl::check(
            'conciliacion_item',
            'chk_conciliacion_item_resultado',
            "resultado IN ('COINCIDE','DIFERENCIA_MONTO','NO_REGISTRADA')",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_item');
        Schema::dropIfExists('conciliacion');
    }
};
