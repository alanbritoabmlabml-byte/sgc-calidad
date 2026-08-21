<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            // Correlativo por proceso, replica el N° preimpreso de la boleta fisica: IT-000001
            $table->string('code')->unique();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('process_id')->constrained()->cascadeOnDelete();
            // Se congela la plantilla usada, para que una revision futura no altere boletas ya emitidas.
            $table->foreignId('test_template_id')->constrained()->restrictOnDelete();

            $table->date('fecha');
            $table->time('hora')->nullable();
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operador')->nullable();
            $table->string('responsable')->nullable();
            $table->string('turno')->nullable();

            // Cantidades del proceso (Corte y Costura: total de bolsas / falladas / buenas)
            $table->unsignedInteger('total_unidades')->nullable();
            $table->unsignedInteger('total_falladas')->nullable();

            $table->text('observacion')->nullable();
            // Nota que NO se muestra en la boleta publica del cliente.
            $table->text('observacion_interna')->nullable();

            // PENDIENTE | P.C | P.OBS | RECHAZADO
            $table->string('estado')->default('PENDIENTE');

            // Token del QR. Aleatorio, no adivinable, no correlativo.
            $table->string('public_token', 32)->unique();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('etiquetas_impresas_at')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['process_id', 'fecha']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
