<?php

use App\Enumerados\EstadoReserva;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reservas (HU-07 / HU-09 / HU-23).
 *
 * El borrado es lógico: una reserva nunca se elimina de verdad, para no perder
 * el histórico del cliente ni falsear los reportes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserva', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_reserva', 20)->unique();
            $table->foreignId('inmueble_id')->constrained('inmueble')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuario')->restrictOnDelete();
            $table->foreignId('asesor_id')->nullable()->constrained('usuario')->nullOnDelete();
            $table->decimal('monto_reserva', 12, 2)->default(0);
            $table->enum('estado', EstadoReserva::valores())->default(EstadoReserva::PendientePago->value);
            $table->timestamp('expira_en');
            $table->text('notas_cliente')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['inmueble_id', 'estado']);
            $table->index(['usuario_id', 'estado']);
            $table->index('expira_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva');
    }
};
