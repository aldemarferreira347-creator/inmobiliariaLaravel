<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de los cambios de estado de una reserva.
 *
 * Tabla de solo inserción: nunca se modifica ni se elimina. `cambiado_por`
 * nulo significa que el cambio lo originó el sistema (la tarea programada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_reserva', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reserva')->cascadeOnDelete();
            $table->string('estado_anterior', 50)->nullable();
            $table->string('estado_nuevo', 50);
            $table->foreignId('cambiado_por')->nullable()->constrained('usuario')->nullOnDelete();
            $table->text('comentario')->nullable();
            $table->string('ip_origen', 45)->nullable();
            $table->timestamp('creado_en')->useCurrent();

            $table->index(['reserva_id', 'creado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_reserva');
    }
};
