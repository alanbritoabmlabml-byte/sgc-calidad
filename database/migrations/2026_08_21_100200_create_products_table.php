<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->string('code');                       // "Bl 65x108/66"
            $table->string('name')->nullable();
            $table->string('color')->nullable();
            $table->decimal('ancho_nominal', 8, 2)->nullable();    // cm
            $table->decimal('largo_nominal', 8, 2)->nullable();    // cm
            $table->decimal('gramaje_nominal', 8, 2)->nullable();  // g/m2
            $table->decimal('denier_nominal', 8, 2)->nullable();
            $table->decimal('peso_nominal', 8, 2)->nullable();     // g por bolsa
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['sector_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
