<?php

declare(strict_types=1);

use Database\Support\PostgresDdl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paquete Referencias: copias locales de datos de MS-01 y MS-02, alimentadas
 * por eventos. Database per Service -- ninguna FK sale del microservicio, los
 * ids de otros servicios son UUID "blandos".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_anfitrion', function (Blueprint $table): void {
            $table->uuid('anfitrion_id')->primary();        // ref MS-01 (= sub del JWT de Keycloak)
            $table->string('nombre_completo', 150);
            $table->string('nit', 20)->nullable();
            $table->string('razon_social', 150)->nullable();
            $table->string('estado_verificacion', 15)->default('PENDIENTE');
            $table->timestampTz('sincronizado_en')->useCurrent();
        });

        PostgresDdl::check(
            'ref_anfitrion',
            'chk_ref_anfitrion_estado',
            "estado_verificacion IN ('PENDIENTE','VERIFICADO','SUSPENDIDO','BLOQUEADO')",
        );

        Schema::create('ref_reserva', function (Blueprint $table): void {
            $table->uuid('reserva_id')->primary();          // ref MS-02
            $table->uuid('cliente_id');                     // ref MS-01
            $table->uuid('anfitrion_id');                   // ref MS-01
            $table->uuid('alojamiento_id');                 // ref MS-01
            $table->string('categoria_alojamiento', 30)->nullable();
            $table->date('fecha_checkin');
            $table->date('fecha_checkout');
            $table->smallInteger('noches')->storedAs('fecha_checkout - fecha_checkin');
            $table->string('estado', 20)->default('SOLICITADA');
            $table->timestampTz('sincronizado_en')->useCurrent();

            $table->index(['anfitrion_id', 'estado'], 'idx_ref_reserva_anfitrion');
        });

        PostgresDdl::check(
            'ref_reserva',
            'chk_ref_reserva_estado',
            "estado IN ('SOLICITADA','CONFIRMADA','EN_CURSO','COMPLETADA','CANCELADA','NO_SHOW')",
        );
        PostgresDdl::check('ref_reserva', 'chk_ref_reserva_fechas', 'fecha_checkout > fecha_checkin');
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_reserva');
        Schema::dropIfExists('ref_anfitrion');
    }
};
