<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_template_id')->constrained()->cascadeOnDelete();
            $table->string('code');                       // gramaje, ancho, tension
            $table->string('label');                      // "Gramaje Promedio"
            $table->string('unit')->nullable();           // "g/m2", "cm", "kgf", "%"
            $table->string('tipo')->default('numeric');   // numeric|text|select|bool
            // Subdivision del parametro: "T" (trama) / "U" (urdimbre). Null = sin subdivision.
            $table->string('grupo')->nullable();

            // --- Especificacion ---
            // Modo: rango (min/max), objetivo+-tolerancia, objetivo+-porcentaje, minimo, maximo, libre
            $table->string('spec_modo')->default('libre');
            $table->decimal('spec_min', 12, 3)->nullable();
            $table->decimal('spec_max', 12, 3)->nullable();
            $table->decimal('spec_objetivo', 12, 3)->nullable();
            $table->decimal('spec_tolerancia', 12, 3)->nullable();   // absoluta
            $table->decimal('spec_tolerancia_pct', 8, 3)->nullable(); // porcentual (denier +-3,5%)
            // Etiqueta tal como figura en la planilla: "±2", "MINIMO 60Kgrsf", "23 ± 5"
            $table->string('spec_label')->nullable();
            // Si es true, la especificacion se toma del producto (gramaje/ancho nominal) y no de aqui.
            $table->string('spec_desde_producto')->nullable(); // ancho_nominal|largo_nominal|gramaje_nominal|denier_nominal|peso_nominal

            $table->unsignedTinyInteger('muestras')->default(1);  // 1 = valor unico; N = M1..MN
            $table->boolean('promediar')->default(true);
            $table->json('opciones')->nullable();                 // para tipo=select: ["B","M"]
            $table->unsignedInteger('orden')->default(1);
            $table->boolean('requerido')->default(false);
            $table->timestamps();

            $table->unique(['test_template_id', 'code', 'grupo'], 'test_params_unique');
            $table->index(['test_template_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_parameters');
    }
};
