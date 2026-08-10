<?php

use App\Enumerados\EstadoPago;
use App\Enumerados\MetodoPago;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos de una reserva (HU-21.1 / HU-23).
 *
 * El monto se guarda en pesos, no en centavos. `referencia` es el número de
 * transacción que declara el cliente; cuando se integre una pasarela, sus
 * identificadores se guardarán en `referencia_pasarela` sin cambiar el resto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reserva')->cascadeOnDelete();
            $table->foreignId('revisado_por')->nullable()->constrained('usuario')->nullOnDelete();
            $table->enum('metodo_pago', MetodoPago::valores());
            $table->char('moneda', 3)->default('COP');
            $table->decimal('monto', 12, 2);
            $table->string('referencia', 150)->nullable();
            $table->string('referencia_pasarela', 150)->nullable();
            $table->enum('estado', EstadoPago::valores())->default(EstadoPago::Pendiente->value);
            $table->string('motivo_rechazo')->nullable();
            $table->timestamp('revisado_en')->nullable();
            $table->timestamps();

            $table->index(['reserva_id', 'estado']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago');
    }
};
