<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversaciones cliente ↔ asesor sobre un inmueble (HU-13).
 *
 * El esquema original tenía una columna `hilo_id` que nunca llegó a usarse y
 * agrupaba los mensajes en consulta, por pares de emisor y receptor. Aquí el
 * hilo es una entidad de primera clase: una fila por cliente, asesor e inmueble.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('usuario')->cascadeOnDelete();
            $table->foreignId('asesor_id')->constrained('usuario')->cascadeOnDelete();
            $table->foreignId('inmueble_id')->nullable()->constrained('inmueble')->nullOnDelete();
            $table->timestamp('ultimo_mensaje_en')->nullable();
            $table->timestamps();

            $table->unique(['cliente_id', 'asesor_id', 'inmueble_id']);
            $table->index(['asesor_id', 'ultimo_mensaje_en']);
            $table->index(['cliente_id', 'ultimo_mensaje_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversacion');
    }
};
