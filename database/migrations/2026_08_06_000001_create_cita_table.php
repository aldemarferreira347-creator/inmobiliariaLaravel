<?php

use App\Enumerados\EstadoCita;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Citas de visita a un inmueble (HU-10 / HU-11 / HU-12 / HU-27).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cita', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('usuario')->cascadeOnDelete();
            $table->foreignId('asesor_id')->nullable()->constrained('usuario')->nullOnDelete();
            $table->foreignId('inmueble_id')->constrained('inmueble')->cascadeOnDelete();
            $table->dateTime('fecha');
            $table->enum('estado', EstadoCita::valores())->default(EstadoCita::Pendiente->value);
            $table->timestamps();

            $table->index(['asesor_id', 'estado']);
            $table->index(['cliente_id', 'estado']);
            $table->index(['inmueble_id', 'fecha']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cita');
    }
};
