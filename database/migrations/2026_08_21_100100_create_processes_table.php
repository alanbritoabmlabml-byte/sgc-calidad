<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('boleta_code')->nullable();  // COD.02, COD.03
            $table->unsignedInteger('orden')->default(1);
            // Si es true, un lote RECHAZADO en este proceso bloquea el avance al siguiente.
            $table->boolean('bloquea_siguiente')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['sector_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processes');
    }
};
