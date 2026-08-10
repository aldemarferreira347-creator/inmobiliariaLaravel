<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de eventos de webhook ya procesados (HU-23), para que un mismo
 * evento reenviado por la pasarela (reintento de red, etc.) nunca se aplique
 * dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_evento', function (Blueprint $table) {
            $table->id();
            $table->string('pasarela', 20);
            $table->string('evento_id', 255);
            $table->string('tipo', 100);
            $table->timestamp('procesado_en')->nullable();
            $table->timestamps();

            $table->unique(['pasarela', 'evento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_evento');
    }
};
