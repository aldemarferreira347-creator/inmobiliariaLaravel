<?php

use App\Enumerados\TipoNotificacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notificaciones in-app (HU-15 / HU-22).
 *
 * `referencia_tipo` y `referencia_id` permiten enlazar la notificación con la
 * entidad que la originó, para llevar al usuario directamente a ella.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuario')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('mensaje');
            $table->enum('tipo', TipoNotificacion::valores())->default(TipoNotificacion::Info->value);
            $table->string('referencia_tipo', 50)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->timestamp('leida_en')->nullable();
            $table->timestamp('creado_en')->useCurrent();

            $table->index(['usuario_id', 'leida_en']);
            $table->index(['referencia_tipo', 'referencia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacion');
    }
};
