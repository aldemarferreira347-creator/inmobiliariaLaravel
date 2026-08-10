<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensajes de una conversación (HU-13).
 *
 * El adjunto tiene columna propia: el prototipo lo incrustaba dentro del texto
 * con un byte de control para no alterar el esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensaje', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversacion_id')->constrained('conversacion')->cascadeOnDelete();
            $table->foreignId('emisor_id')->constrained('usuario')->cascadeOnDelete();
            $table->text('contenido')->nullable();
            $table->string('adjunto_url')->nullable();
            $table->timestamp('leido_en')->nullable();
            $table->timestamp('creado_en')->useCurrent();

            $table->index(['conversacion_id', 'id']);
            $table->index(['conversacion_id', 'leido_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensaje');
    }
};
