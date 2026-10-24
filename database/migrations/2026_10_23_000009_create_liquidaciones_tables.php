<?php

declare(strict_types=1);

use Database\Support\PostgresDdl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paquete Liquidaciones (F05 + F16): la comision de cada pago capturado y la
 * liquidacion que se le paga al anfitrion.
 *
 * `comision` guarda el porcentaje aplicado (snapshot): si manana cambia la
 * regla de monetizacion, el historico no se mueve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidacion', function (Blueprint $table): void {
            $table->uuid('liquidacion_id')->primary();
            $table->uuid('anfitrion_id');
            $table->uuid('metodo_id')->nullable();
            $table->date('periodo_desde');
            $table->date('periodo_hasta');
            $table->decimal('total_bruto', 12, 2)->default(0);
            $table->decimal('total_comisiones', 12, 2)->default(0);
            $table->decimal('total_reembolsos', 12, 2)->default(0);
            $table->decimal('total_neto', 12, 2)->default(0);
            $table->char('moneda', 3)->default('BOB');
            $table->string('estado', 12)->default('GENERADA');
            $table->uuid('aprobado_por')->nullable();        // ref MS-01 (administrador)
            $table->timestampTz('aprobado_en')->nullable();
            $table->timestampTz('pagado_en')->nullable();
            $table->string('referencia_transferencia', 100)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('anfitrion_id')->references('anfitrion_id')->on('ref_anfitrion');
            $table->foreign('metodo_id')->references('metodo_id')->on('metodo_cobro_anfitrion');
            $table->index(['anfitrion_id', 'estado'], 'idx_liquidacion_anfitrion');
        });

        PostgresDdl::defaultUuid('liquidacion', 'liquidacion_id');
        PostgresDdl::check(
            'liquidacion',
            'chk_liquidacion_estado',
            "estado IN ('GENERADA','APROBADA','PAGADA','RECHAZADA','ANULADA')",
        );
        PostgresDdl::check('liquidacion', 'chk_liquidacion_periodo', 'periodo_hasta >= periodo_desde');
        // neto = bruto - comisiones - reembolsos, garantizado por la base.
        PostgresDdl::check(
            'liquidacion',
            'chk_liquidacion_neto',
            'total_neto = total_bruto - total_comisiones - total_reembolsos',
        );

        Schema::create('comision', function (Blueprint $table): void {
            $table->uuid('comision_id')->primary();
            $table->uuid('pago_id')->unique();               // 1 pago capturado = 1 comision
            $table->uuid('reserva_id');
            $table->uuid('anfitrion_id');
            $table->uuid('regla_id')->nullable();
            $table->uuid('acuerdo_id')->nullable();
            $table->uuid('liquidacion_id')->nullable();      // NULL hasta que se liquide
            $table->decimal('base_comisionable', 12, 2);
            $table->decimal('porcentaje_aplicado', 5, 2)->nullable();
            $table->decimal('monto_comision', 12, 2);
            $table->decimal('monto_neto_anfitrion', 12, 2);
            $table->char('moneda', 3)->default('BOB');
            $table->string('estado', 12)->default('RETENIDA');
            $table->date('liquidable_desde')->nullable();    // check-out + dias_liquidacion del acuerdo
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('pago_id')->references('pago_id')->on('pago');
            $table->foreign('reserva_id')->references('reserva_id')->on('ref_reserva');
            $table->foreign('anfitrion_id')->references('anfitrion_id')->on('ref_anfitrion');
            $table->foreign('regla_id')->references('regla_id')->on('regla_monetizacion');
            $table->foreign('acuerdo_id')->references('acuerdo_id')->on('acuerdo_comercial');
            $table->foreign('liquidacion_id')->references('liquidacion_id')->on('liquidacion');
            $table->index(['anfitrion_id', 'estado', 'liquidable_desde'], 'idx_comision_liquidable');
        });

        PostgresDdl::defaultUuid('comision', 'comision_id');
        PostgresDdl::check('comision', 'chk_comision_base', 'base_comisionable >= 0');
        PostgresDdl::check('comision', 'chk_comision_monto', 'monto_comision >= 0');
        PostgresDdl::check(
            'comision',
            'chk_comision_estado',
            "estado IN ('RETENIDA','LIQUIDABLE','LIQUIDADA','REVERTIDA')",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('comision');
        Schema::dropIfExists('liquidacion');
    }
};
