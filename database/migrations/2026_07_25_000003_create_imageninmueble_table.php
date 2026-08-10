<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Galería de imágenes de cada inmueble (HU-08). `inmueble.imagen` refleja la portada.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imageninmueble', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inmueble_id')->constrained('inmueble')->cascadeOnDelete();
            $table->string('url');
            $table->boolean('es_principal')->default(false);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamp('creado_en')->useCurrent();

            $table->index(['inmueble_id', 'es_principal']);
            $table->index(['inmueble_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imageninmueble');
    }
};
