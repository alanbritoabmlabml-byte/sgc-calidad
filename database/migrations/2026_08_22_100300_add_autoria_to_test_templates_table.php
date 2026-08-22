<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quien creo la plantilla y cuando. Calidad puede crear plantillas y
        // revisiones nuevas pero no modificarlas: el registro de autoria es lo
        // que hace auditable ese cambio de especificacion.
        Schema::table('test_templates', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('active')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('activada_at')->nullable()->after('created_by');
            $table->foreignId('activada_por')->nullable()->after('activada_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('test_parameters', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('requerido')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('test_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('activada_por');
            $table->dropColumn('activada_at');
        });

        Schema::table('test_parameters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
