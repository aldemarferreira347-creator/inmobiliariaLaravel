<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas del asesor tras completar una visita (HU-12.1). Una cita tiene, como
 * mucho, una observación: se actualiza (upsert) en vez de acumular filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observacioncita', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_id')->constrained('cita')->cascadeOnDelete();
            $table->foreignId('asesor_id')->nullable()->constrained('usuario')->nullOnDelete();
            $table->text('descripcion');
            $table->timestamps();

            $table->unique('cita_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observacioncita');
    }
};
