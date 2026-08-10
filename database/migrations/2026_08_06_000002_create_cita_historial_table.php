<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de cambios en la asignación/estado de una cita (HU-10.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cita_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_id')->constrained('cita')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuario')->cascadeOnDelete();
            $table->string('accion', 50);
            $table->text('descripcion')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('cita_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cita_historial');
    }
};
