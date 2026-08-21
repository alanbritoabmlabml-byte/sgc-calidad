<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_parameter_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('muestra')->default(1);  // 1..13
            $table->decimal('valor_num', 12, 3)->nullable();
            $table->string('valor_texto')->nullable();
            // Se calcula al guardar contra la especificacion vigente del parametro.
            $table->boolean('en_especificacion')->nullable();
            $table->timestamps();

            $table->unique(['inspection_id', 'test_parameter_id', 'muestra'], 'measurement_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
    }
};
