<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baja logica de los metodos de cobro: en vez de borrar la cuenta (que pudo
 * recibir liquidaciones) se marca `activo = false`. No hay endpoint DELETE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metodo_cobro_anfitrion', function (Blueprint $table): void {
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('metodo_cobro_anfitrion', function (Blueprint $table): void {
            $table->dropColumn('activo');
        });
    }
};
