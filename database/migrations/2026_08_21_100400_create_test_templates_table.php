<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_id')->constrained()->cascadeOnDelete();
            $table->string('name');                                // "Ensayos de Calidad en Bolsas de Polipropileno"
            $table->string('revision', 16)->default('1');          // "Rev:2" de la planilla
            $table->unsignedTinyInteger('muestras_default')->default(8);
            $table->unsignedTinyInteger('muestras_max')->default(13);
            // Si es true, la plantilla vigente para el proceso. Solo una activa por proceso.
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['process_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_templates');
    }
};
