<?php

use App\Enumerados\EstadoInmueble;
use App\Enumerados\ModalidadInmueble;
use App\Enumerados\TipoInmueble;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inmuebles (HU-01 / HU-02 / HU-04 / HU-08).
 *
 * `modalidad` es un enum real de tres valores: a diferencia del esquema
 * original, «ambos» ya no necesita persistirse como «venta».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inmueble', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('titulo', 150);
            $table->text('descripcion');
            $table->enum('tipo', TipoInmueble::valores());
            $table->enum('modalidad', ModalidadInmueble::valores());
            $table->enum('estado', EstadoInmueble::valores())->default(EstadoInmueble::Disponible->value);
            $table->decimal('precio_venta', 12, 2)->nullable();
            $table->decimal('precio_arrendamiento', 12, 2)->nullable();
            $table->string('ciudad', 100);
            $table->string('barrio', 100);
            $table->string('direccion');
            $table->unsignedInteger('habitaciones')->default(1);
            $table->unsignedInteger('banos')->default(1);
            $table->decimal('area', 10, 2);
            $table->boolean('parqueadero')->default(false);
            $table->string('imagen')->nullable();
            $table->timestamp('fecha_publicacion')->useCurrent();

            $table->index('estado');
            $table->index('ciudad');
            $table->index('modalidad');
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inmueble');
    }
};
