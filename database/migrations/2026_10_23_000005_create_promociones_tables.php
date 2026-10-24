<?php

declare(strict_types=1);

use Database\Support\PostgresDdl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paquete Promociones (F09): descuentos, cupones, temporadas y estadias prolongadas.
 *
 * `financiada_por` decide quien paga el descuento: si lo financia el anfitrion,
 * la base comisionable baja; si lo financia la plataforma, no.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promocion', function (Blueprint $table): void {
            $table->uuid('promocion_id')->primary();
            $table->string('codigo', 30)->nullable()->unique();     // cupon opcional
            $table->string('nombre', 100);
            $table->string('tipo', 20);
            $table->string('tipo_descuento', 12);                   // PORCENTAJE | MONTO_FIJO
            $table->decimal('valor', 12, 2);
            $table->char('moneda', 3)->default('BOB');
            $table->string('creada_por_rol', 10);                   // ANFITRION | ADMIN
            $table->uuid('creada_por');                             // ref MS-01
            $table->uuid('anfitrion_id')->nullable();               // NULL = promo de la plataforma
            $table->string('financiada_por', 10)->default('ANFITRION');
            $table->smallInteger('min_noches')->default(1);
            $table->timestampTz('vigente_desde');
            $table->timestampTz('vigente_hasta');
            $table->integer('max_usos')->nullable();                // NULL = ilimitado
            $table->integer('usos_actuales')->default(0);
            $table->string('estado', 10)->default('BORRADOR');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('anfitrion_id')->references('anfitrion_id')->on('ref_anfitrion');
            $table->index(['estado', 'vigente_desde', 'vigente_hasta'], 'idx_promocion_vigencia');
        });

        PostgresDdl::defaultUuid('promocion', 'promocion_id');
        PostgresDdl::check(
            'promocion',
            'chk_promocion_tipo',
            "tipo IN ('TEMPORADA','FECHA_ESPECIAL','ESTADIA_PROLONGADA','CUPON','ULTIMO_MINUTO')",
        );
        PostgresDdl::check(
            'promocion',
            'chk_promocion_tipo_descuento',
            "tipo_descuento IN ('PORCENTAJE','MONTO_FIJO')",
        );
        PostgresDdl::check('promocion', 'chk_promocion_valor', 'valor > 0');
        PostgresDdl::check('promocion', 'chk_promocion_rol', "creada_por_rol IN ('ANFITRION','ADMIN')");
        PostgresDdl::check(
            'promocion',
            'chk_promocion_financiada_por',
            "financiada_por IN ('ANFITRION','PLATAFORMA')",
        );
        PostgresDdl::check(
            'promocion',
            'chk_promocion_estado',
            "estado IN ('BORRADOR','ACTIVA','PAUSADA','VENCIDA','CANCELADA')",
        );
        PostgresDdl::check('promocion', 'chk_promocion_vigencia', 'vigente_hasta > vigente_desde');
        // Nunca se puede pasar del tope de usos.
        PostgresDdl::check('promocion', 'chk_promocion_usos', 'max_usos IS NULL OR usos_actuales <= max_usos');
        // Un descuento porcentual no puede pasar de 100 %.
        PostgresDdl::check(
            'promocion',
            'chk_promocion_porcentaje_maximo',
            "tipo_descuento <> 'PORCENTAJE' OR valor <= 100",
        );

        Schema::create('promocion_alojamiento', function (Blueprint $table): void {
            $table->uuid('promocion_id');
            $table->uuid('alojamiento_id');                 // ref MS-01

            $table->primary(['promocion_id', 'alojamiento_id']);
            $table->foreign('promocion_id')->references('promocion_id')->on('promocion')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promocion_alojamiento');
        Schema::dropIfExists('promocion');
    }
};
