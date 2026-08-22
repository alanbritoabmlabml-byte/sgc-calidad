<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Ficha tecnica del producto. Los nominales que ya existian
            // (ancho, largo, gramaje, denier, peso) alimentan las tolerancias
            // del ensayo; estos campos son la especificacion declarada del
            // producto, que acompana al certificado.
            $table->string('norma')->nullable()->after('color');
            $table->string('tipo_tejido')->nullable()->after('norma');
            $table->string('tratamiento_uv')->nullable()->after('tipo_tejido');
            $table->string('cliente')->nullable()->after('tratamiento_uv');

            $table->decimal('capacidad_kg', 8, 2)->nullable()->after('peso_nominal');
            $table->decimal('densidad_urdimbre_nominal', 8, 2)->nullable()->after('capacidad_kg');
            $table->decimal('densidad_trama_nominal', 8, 2)->nullable()->after('densidad_urdimbre_nominal');

            $table->text('observaciones_tecnicas')->nullable()->after('densidad_trama_nominal');

            // Quien y cuando toco la ficha tecnica por ultima vez.
            $table->foreignId('actualizado_por')->nullable()->after('observaciones_tecnicas')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('ficha_actualizada_at')->nullable()->after('actualizado_por');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actualizado_por');
            $table->dropColumn([
                'norma', 'tipo_tejido', 'tratamiento_uv', 'cliente',
                'capacidad_kg', 'densidad_urdimbre_nominal', 'densidad_trama_nominal',
                'observaciones_tecnicas', 'ficha_actualizada_at',
            ]);
        });
    }
};
