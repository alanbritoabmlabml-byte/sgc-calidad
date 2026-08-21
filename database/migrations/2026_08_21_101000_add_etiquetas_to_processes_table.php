<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            // Rotulos de la boleta impresa, tal como figuran en el formulario
            // preimpreso de cada proceso. Tejido dice "Fecha de corte de telar"
            // y Corte y Costura "Fecha de corte de rollo": el texto es dato,
            // no codigo, para que cada proceso imprima con sus propias palabras.
            $table->json('etiquetas')->nullable()->after('boleta_code');
        });
    }

    public function down(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->dropColumn('etiquetas');
        });
    }
};
