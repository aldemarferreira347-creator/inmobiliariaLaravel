<?php

use App\Enumerados\PasarelaPago;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarjetas tokenizadas por el cliente (HU-20). Solo se guarda lo que la
 * pasarela devuelve como referencia (token, marca, últimos 4 dígitos):
 * NUNCA el número completo, CVV ni fecha de expiración completa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metodo_pago_guardado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('usuario')->cascadeOnDelete();
            $table->enum('pasarela', PasarelaPago::valores())->default(PasarelaPago::Stripe->value);
            $table->string('token_pasarela', 255);
            $table->string('cliente_pasarela_id', 255)->nullable();
            $table->string('marca', 20)->nullable();
            $table->char('ultimos_4', 4)->nullable();
            $table->string('nombre_titular', 150)->nullable();
            $table->unsignedTinyInteger('mes_expiracion')->nullable();
            $table->unsignedSmallInteger('anio_expiracion')->nullable();
            $table->boolean('predeterminado')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamp('creado_en')->useCurrent();

            $table->unique(['pasarela', 'token_pasarela']);
            $table->index(['cliente_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metodo_pago_guardado');
    }
};
