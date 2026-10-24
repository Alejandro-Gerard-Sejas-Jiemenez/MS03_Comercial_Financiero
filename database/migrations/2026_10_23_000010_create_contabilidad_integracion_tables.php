<?php

declare(strict_types=1);

use Database\Support\PostgresDdl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Libro contable auditable e integracion (Saga + Outbox + Idempotencia).
 *
 * `movimiento_contable` es append-only: una correccion se hace con un asiento
 * de reversa, nunca editando el anterior. La regla SUM(DEBITO) = SUM(CREDITO)
 * por `asiento_id` la valida el servicio de dominio `LibroContable`.
 */
return new class extends Migration
{
    public function up(): void
    {
        PostgresDdl::ejecutar(<<<'SQL'
            CREATE TABLE movimiento_contable (
                movimiento_id   BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                asiento_id      UUID NOT NULL,
                cuenta          VARCHAR(25) NOT NULL
                    CHECK (cuenta IN ('PASARELA','INGRESO_COMISION','INGRESO_CARGO_SERVICIO',
                                      'POR_PAGAR_ANFITRION','REEMBOLSOS','PROMOCIONES_PLATAFORMA')),
                tipo            VARCHAR(7) NOT NULL CHECK (tipo IN ('DEBITO','CREDITO')),
                monto           NUMERIC(12,2) NOT NULL CHECK (monto > 0),
                moneda          CHAR(3) NOT NULL DEFAULT 'BOB',
                origen_tipo     VARCHAR(20) NOT NULL
                    CHECK (origen_tipo IN ('PAGO','REEMBOLSO','COMISION','LIQUIDACION')),
                origen_id       UUID NOT NULL,
                anfitrion_id    UUID,
                created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
            )
            SQL);
        PostgresDdl::ejecutar('CREATE INDEX idx_mov_asiento ON movimiento_contable (asiento_id)');
        PostgresDdl::ejecutar('CREATE INDEX idx_mov_anfitrion ON movimiento_contable (anfitrion_id, cuenta)');
        PostgresDdl::appendOnly('movimiento_contable', 'trg_movimiento_inmutable');

        Schema::create('outbox_evento', function (Blueprint $table): void {
            $table->uuid('evento_id')->primary();
            $table->string('agregado_tipo', 30);             // pago | liquidacion | acuerdo_comercial...
            $table->uuid('agregado_id');
            $table->string('tipo_evento', 60);               // routing key: pago.capturado
            $table->jsonb('payload');
            $table->uuid('saga_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('publicado_en')->nullable(); // NULL = pendiente de publicar
            $table->smallInteger('intentos')->default(0);
        });

        PostgresDdl::defaultUuid('outbox_evento', 'evento_id');
        // Indice parcial: el relay solo mira lo pendiente, no la tabla entera.
        PostgresDdl::ejecutar(
            'CREATE INDEX idx_outbox_pendientes ON outbox_evento (created_at) WHERE publicado_en IS NULL',
        );

        Schema::create('evento_procesado', function (Blueprint $table): void {
            $table->uuid('mensaje_id')->primary();           // id del evento recibido de RabbitMQ
            $table->string('tipo_evento', 60);
            $table->string('origen', 10);                    // MS-01 | MS-02 | MS-04
            $table->timestampTz('procesado_en')->useCurrent();
        });

        PostgresDdl::ejecutar(<<<'SQL'
            CREATE TABLE auditoria_log (
                auditoria_id    BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                usuario_id      UUID,
                accion          VARCHAR(40) NOT NULL,
                entidad         VARCHAR(40) NOT NULL,
                entidad_id      UUID,
                antes           JSONB,
                despues         JSONB,
                ip              INET,
                created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
            )
            SQL);
        PostgresDdl::ejecutar('CREATE INDEX idx_auditoria_entidad ON auditoria_log (entidad, entidad_id)');
    }

    public function down(): void
    {
        PostgresDdl::ejecutar('DROP TABLE IF EXISTS auditoria_log');
        Schema::dropIfExists('evento_procesado');
        Schema::dropIfExists('outbox_evento');
        PostgresDdl::ejecutar('DROP TRIGGER IF EXISTS trg_movimiento_inmutable ON movimiento_contable');
        PostgresDdl::ejecutar('DROP TABLE IF EXISTS movimiento_contable');
    }
};
