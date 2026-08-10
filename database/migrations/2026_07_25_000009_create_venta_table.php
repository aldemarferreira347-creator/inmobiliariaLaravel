<?php

use App\Enumerados\EstadoVenta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ventas gestionadas por el asesor (HU-14 / HU-19.2 / HU-21.3)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inmueble_id')->constrained('inmueble')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuario')->restrictOnDelete();
            $table->foreignId('asesor_id')->nullable()->constrained('usuario')->nullOnDelete();
            $table->decimal('precio_venta', 12, 2);
            $table->date('fecha_venta');
            $table->string('notaria', 150)->nullable();
            $table->string('escritura_ruta')->nullable();
            $table->enum('estado', EstadoVenta::valores())->default(EstadoVenta::EnProceso->value);
            $table->timestamps();

            $table->index(['inmueble_id', 'estado']);
            $table->index(['usuario_id', 'estado']);
            $table->index('fecha_venta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta');
    }
};
