<?php

declare(strict_types=1);

use Database\Support\PostgresDdl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paquete Acuerdos (F17): acuerdo comercial con el anfitrion y su metodo de cobro.
 *
 * Dos invariantes las garantiza la base: un solo acuerdo ACTIVO por anfitrion y
 * un solo metodo de cobro principal por anfitrion (indices unicos parciales).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acuerdo_comercial', function (Blueprint $table): void {
            $table->uuid('acuerdo_id')->primary();
            $table->uuid('anfitrion_id');
            $table->uuid('regla_comision_id')->nullable();  // tarifa negociada; NULL = la general
            $table->string('estado', 12)->default('BORRADOR');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->jsonb('condiciones')->default('{}');    // plazo de liquidacion, retenciones, clausulas
            $table->smallInteger('dias_liquidacion')->default(7);
            $table->uuid('aprobado_por')->nullable();       // ref MS-01 (administrador)
            $table->timestampTz('aprobado_en')->nullable();
            $table->text('motivo_estado')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('anfitrion_id')->references('anfitrion_id')->on('ref_anfitrion');
            $table->foreign('regla_comision_id')->references('regla_id')->on('regla_monetizacion');
        });

        PostgresDdl::defaultUuid('acuerdo_comercial', 'acuerdo_id');
        PostgresDdl::check(
            'acuerdo_comercial',
            'chk_acuerdo_estado',
            "estado IN ('BORRADOR','ACTIVO','SUSPENDIDO','FINALIZADO','RESCINDIDO')",
        );
        PostgresDdl::check(
            'acuerdo_comercial',
            'chk_acuerdo_dias_liquidacion',
            'dias_liquidacion BETWEEN 0 AND 60',
        );
        PostgresDdl::check(
            'acuerdo_comercial',
            'chk_acuerdo_fechas',
            'fecha_fin IS NULL OR fecha_fin >= fecha_inicio',
        );
        // Un anfitrion solo puede tener UN acuerdo ACTIVO a la vez.
        PostgresDdl::uniqueParcial(
            'uq_acuerdo_activo_por_anfitrion',
            'acuerdo_comercial',
            'anfitrion_id',
            "estado = 'ACTIVO'",
        );

        Schema::create('metodo_cobro_anfitrion', function (Blueprint $table): void {
            $table->uuid('metodo_id')->primary();
            $table->uuid('anfitrion_id');
            $table->string('tipo', 15);                     // CUENTA_BANCARIA | QR | BILLETERA
            $table->string('banco', 80)->nullable();
            $table->string('titular', 150);
            $table->text('numero_cifrado');                 // cifrado con Laravel Crypt
            $table->char('ultimos_digitos', 4)->nullable();
            $table->boolean('es_principal')->default(false);
            $table->boolean('verificado')->default(false);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('anfitrion_id')->references('anfitrion_id')->on('ref_anfitrion');
        });

        PostgresDdl::defaultUuid('metodo_cobro_anfitrion', 'metodo_id');
        PostgresDdl::check(
            'metodo_cobro_anfitrion',
            'chk_metodo_tipo',
            "tipo IN ('CUENTA_BANCARIA','QR','BILLETERA')",
        );
        PostgresDdl::uniqueParcial(
            'uq_metodo_principal',
            'metodo_cobro_anfitrion',
            'anfitrion_id',
            'es_principal',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('metodo_cobro_anfitrion');
        Schema::dropIfExists('acuerdo_comercial');
    }
};
