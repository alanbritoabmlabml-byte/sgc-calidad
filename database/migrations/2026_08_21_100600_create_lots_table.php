<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            // Codigo generado por el sistema: RAF-26-00001. Es la clave del QR.
            $table->string('code')->unique();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();

            // Identificadores REALES de planta (los que hoy se escriben a mano en la boleta)
            $table->string('nro_tarjeta')->nullable();          // "N° de Tarjeta" / "Nº ROLLO"
            $table->string('nro_lote_produccion')->nullable();  // "Nº LOTE" de produccion / SIMEC

            $table->date('fecha');
            $table->time('hora')->nullable();
            $table->string('turno')->nullable();                // Dia|Noche
            $table->decimal('peso_neto', 12, 3)->nullable();    // kg del rollo

            // Trazabilidad: de que lote proviene. Tejido -> Corte y Costura.
            $table->foreignId('source_lot_id')->nullable()->constrained('lots')->nullOnDelete();

            // abierto | liberado | bloqueado | cerrado
            $table->string('estado')->default('abierto');
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sector_id', 'fecha']);
            $table->index('estado');
            $table->index('nro_tarjeta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
