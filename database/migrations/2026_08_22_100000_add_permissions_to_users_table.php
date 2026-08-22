<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Permisos concedidos, como lista de claves ("inspecciones.emitir").
            // El rol sigue existiendo, pero solo como plantilla inicial: lo que
            // manda a la hora de autorizar es esta lista, para que el
            // administrador pueda armar combinaciones que no encajan en un rol.
            $table->json('permissions')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
