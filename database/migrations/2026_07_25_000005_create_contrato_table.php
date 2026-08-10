<?php

use App\Enumerados\EstadoContrato;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contratos de arriendo (HU-17). Uno por reserva confirmada.
 * `archivo_ruta` apunta al disco privado: el PDF firmado no se sirve
 * directamente desde public/, sino a través de un controlador que verifica
 * quién lo pide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->unique()->constrained('reserva')->restrictOnDelete();
            $table->string('numero_contrato', 30)->unique();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->decimal('valor_mensual', 12, 2);
            $table->string('archivo_ruta')->nullable();
            $table->enum('estado', EstadoContrato::valores())->default(EstadoContrato::Vigente->value);
            $table->timestamps();

            $table->index('estado');
            $table->index('fecha_fin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato');
    }
};
