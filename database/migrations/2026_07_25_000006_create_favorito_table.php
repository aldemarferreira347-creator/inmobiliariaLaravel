<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Inmuebles marcados como favoritos por un usuario (HU-18)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorito', function (Blueprint $table) {
            $table->foreignId('usuario_id')->constrained('usuario')->cascadeOnDelete();
            $table->foreignId('inmueble_id')->constrained('inmueble')->cascadeOnDelete();
            $table->timestamp('creado_en')->useCurrent();

            $table->primary(['usuario_id', 'inmueble_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorito');
    }
};
