<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotencia de la capa HTTP (header `Idempotency-Key`).
 *
 * Guarda la respuesta de la primera llamada con una clave. Una repeticion con
 * el mismo cuerpo devuelve esa respuesta sin ejecutar nada; con otro cuerpo
 * (hash distinto) se rechaza. Es independiente de `pago.idempotency_key`, que
 * protege la regla de negocio de no cobrar dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotencia_http', function (Blueprint $table): void {
            $table->string('clave', 100);
            $table->string('ruta', 200);
            $table->string('hash_body', 64);
            $table->smallInteger('status');
            $table->jsonb('respuesta');
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['clave', 'ruta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotencia_http');
    }
};
