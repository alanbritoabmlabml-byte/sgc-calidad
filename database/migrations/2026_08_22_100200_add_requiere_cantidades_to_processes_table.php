<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            // Si el proceso lleva conteo de unidades en su boleta. Corte y
            // Costura registra total de bolsas y falladas; Tejido no. Sirve
            // para saber que campos exigir antes de emitir la boleta.
            $table->boolean('requiere_cantidades')->default(false)->after('bloquea_siguiente');
        });
    }

    public function down(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->dropColumn('requiere_cantidades');
        });
    }
};
