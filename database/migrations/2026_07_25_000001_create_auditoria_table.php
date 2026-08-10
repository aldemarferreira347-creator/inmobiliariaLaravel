<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-26: traza de las acciones administrativas sobre entidades del sistema
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuario')->nullOnDelete();
            $table->string('entidad', 50);
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->string('accion', 50);
            $table->text('descripcion')->nullable();
            $table->string('ip_origen', 45)->nullable();
            $table->timestamp('creado_en')->useCurrent();

            $table->index(['entidad', 'entidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
