<?php

declare(strict_types=1);

use Database\Support\PostgresDdl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paquete Pagos (F05), tabla `pago`: el desglose del cobro de una reserva.
 *
 * Aqui esta el dinero, y por eso la base no acepta datos incoherentes:
 *  - `monto_total = subtotal_alojamiento - descuento + cargo_servicio` (CHECK);
 *  - un solo pago "vivo" por reserva (indice unico parcial);
 *  - `idempotency_key` unica, para que un reintento de la saga no cobre dos veces;
 *  - `transaccion_pago` es append-only (trigger): no se reescribe la historia
 *    con la pasarela, se agrega una transaccion nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago', function (Blueprint $table): void {
            $table->uuid('pago_id')->primary();
            $table->uuid('reserva_id');                     // ref MS-02 (copia local)
            $table->uuid('cliente_id');                     // ref MS-01
            $table->uuid('anfitrion_id');
            $table->uuid('promocion_id')->nullable();
            // desglose
            $table->decimal('subtotal_alojamiento', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('cargo_servicio', 12, 2)->default(0);
            $table->decimal('monto_total', 12, 2);
            $table->char('moneda', 3)->default('BOB');
            $table->string('metodo', 15);                   // QR | TARJETA | TRANSFERENCIA | BILLETERA
            $table->string('pasarela', 30)->nullable();
            $table->string('estado', 20)->default('PENDIENTE');
            $table->string('idempotency_key', 80)->unique();
            $table->uuid('saga_id')->nullable();            // correlacion con la saga de MS-02
            $table->timestampTz('pagado_en')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('reserva_id')->references('reserva_id')->on('ref_reserva');
            $table->foreign('anfitrion_id')->references('anfitrion_id')->on('ref_anfitrion');
            $table->foreign('promocion_id')->references('promocion_id')->on('promocion');
            $table->index('reserva_id', 'idx_pago_reserva');
            $table->index(['anfitrion_id', 'estado'], 'idx_pago_anfitrion_estado');
        });

        PostgresDdl::defaultUuid('pago', 'pago_id');
        PostgresDdl::check('pago', 'chk_pago_subtotal', 'subtotal_alojamiento >= 0');
        PostgresDdl::check('pago', 'chk_pago_descuento', 'descuento >= 0');
        PostgresDdl::check('pago', 'chk_pago_cargo_servicio', 'cargo_servicio >= 0');
        PostgresDdl::check('pago', 'chk_pago_monto_total', 'monto_total >= 0');
        PostgresDdl::check('pago', 'chk_pago_moneda', "moneda IN ('BOB','USD')");
        PostgresDdl::check(
            'pago',
            'chk_pago_metodo',
            "metodo IN ('QR','TARJETA','TRANSFERENCIA','BILLETERA')",
        );
        PostgresDdl::check(
            'pago',
            'chk_pago_estado',
            "estado IN ('PENDIENTE','AUTORIZADO','CAPTURADO','FALLIDO','CANCELADO',"
            . "'REEMBOLSADO_PARCIAL','REEMBOLSADO')",
        );
        // La formula del desglose, garantizada por la base y no solo por el dominio.
        PostgresDdl::check(
            'pago',
            'chk_pago_desglose',
            'monto_total = subtotal_alojamiento - descuento + cargo_servicio',
        );
        // Solo un pago "vivo" por reserva.
        PostgresDdl::uniqueParcial(
            'uq_pago_vivo_por_reserva',
            'pago',
            'reserva_id',
            "estado IN ('PENDIENTE','AUTORIZADO','CAPTURADO')",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('pago');
    }
};
