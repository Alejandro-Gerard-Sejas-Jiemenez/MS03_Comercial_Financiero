<?php

declare(strict_types=1);

use Database\Support\PostgresDdl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paquete Pagos (F05): movimientos con la pasarela, reembolsos y comprobantes.
 *
 * `transaccion_pago` es append-only (trigger `prohibir_modificacion`): la
 * historia con la pasarela no se reescribe, se agrega otra transaccion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaccion_pago', function (Blueprint $table): void {
            $table->uuid('transaccion_id')->primary();
            $table->uuid('pago_id');
            $table->string('tipo', 15);                     // AUTORIZACION | CAPTURA | REEMBOLSO | CONTRACARGO
            $table->decimal('monto', 12, 2);
            $table->string('estado', 10);                   // EXITOSA | FALLIDA | PENDIENTE
            $table->string('referencia_pasarela', 100)->nullable();
            $table->jsonb('respuesta_pasarela')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('pago_id')->references('pago_id')->on('pago');
            $table->index('pago_id', 'idx_transaccion_pago');
        });

        PostgresDdl::defaultUuid('transaccion_pago', 'transaccion_id');
        PostgresDdl::check(
            'transaccion_pago',
            'chk_transaccion_tipo',
            "tipo IN ('AUTORIZACION','CAPTURA','REEMBOLSO','CONTRACARGO')",
        );
        PostgresDdl::check('transaccion_pago', 'chk_transaccion_monto', 'monto > 0');
        PostgresDdl::check(
            'transaccion_pago',
            'chk_transaccion_estado',
            "estado IN ('EXITOSA','FALLIDA','PENDIENTE')",
        );
        // La referencia de la pasarela no se puede repetir: es el ancla de la
        // idempotencia del webhook y de la conciliacion.
        PostgresDdl::uniqueParcial(
            'uq_transaccion_ref',
            'transaccion_pago',
            'referencia_pasarela',
            'referencia_pasarela IS NOT NULL',
        );
        PostgresDdl::appendOnly('transaccion_pago', 'trg_transaccion_inmutable');

        Schema::create('reembolso', function (Blueprint $table): void {
            $table->uuid('reembolso_id')->primary();
            $table->uuid('pago_id');
            $table->uuid('transaccion_id')->nullable();
            $table->decimal('monto', 12, 2);
            $table->string('motivo', 30);
            $table->text('detalle')->nullable();
            $table->string('estado', 12)->default('SOLICITADO');
            $table->uuid('solicitado_por')->nullable();     // ref MS-01 / evento de MS-02
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('pago_id')->references('pago_id')->on('pago');
            $table->foreign('transaccion_id')->references('transaccion_id')->on('transaccion_pago');
        });

        PostgresDdl::defaultUuid('reembolso', 'reembolso_id');
        PostgresDdl::check('reembolso', 'chk_reembolso_monto', 'monto > 0');
        PostgresDdl::check(
            'reembolso',
            'chk_reembolso_motivo',
            "motivo IN ('CANCELACION_CLIENTE','CANCELACION_ANFITRION','ERROR_COBRO','DISPUTA','OTRO')",
        );
        PostgresDdl::check(
            'reembolso',
            'chk_reembolso_estado',
            "estado IN ('SOLICITADO','APROBADO','PROCESADO','RECHAZADO')",
        );

        Schema::create('comprobante', function (Blueprint $table): void {
            $table->uuid('comprobante_id')->primary();
            $table->uuid('pago_id');
            $table->string('tipo', 10)->default('RECIBO');  // RECIBO | FACTURA | NOTA_CREDITO
            $table->string('numero', 30)->unique();
            $table->string('nit_cliente', 20)->nullable();
            $table->string('razon_social', 150)->nullable();
            $table->decimal('monto', 12, 2);
            $table->text('archivo_url')->nullable();
            $table->char('hash_documento', 64)->nullable();
            $table->timestampTz('emitido_en')->useCurrent();

            $table->foreign('pago_id')->references('pago_id')->on('pago');
        });

        PostgresDdl::defaultUuid('comprobante', 'comprobante_id');
        PostgresDdl::check(
            'comprobante',
            'chk_comprobante_tipo',
            "tipo IN ('RECIBO','FACTURA','NOTA_CREDITO')",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante');
        Schema::dropIfExists('reembolso');
        PostgresDdl::ejecutar('DROP TRIGGER IF EXISTS trg_transaccion_inmutable ON transaccion_pago');
        Schema::dropIfExists('transaccion_pago');
    }
};
