<?php

use App\Models\ConfigFranjaCita;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Franjas horarias configurables para agendar citas (RF-26.2 / HU-27.1/27.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_franja_cita', function (Blueprint $table) {
            $table->id();
            $table->enum('dia_semana', ConfigFranjaCita::DIAS)->unique();
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->unsignedSmallInteger('intervalo_minutos')->default(30);
            $table->boolean('activo')->default(true);
            $table->foreignId('actualizado_por')->nullable()->constrained('usuario')->nullOnDelete();
            $table->timestamp('actualizado_en')->useCurrent()->useCurrentOnUpdate();
        });

        // Configuración por defecto: lunes a sábado, 8:00-18:00, cada 30 min.
        // Domingo se omite a propósito: sin franja configurada, no hay horarios disponibles.
        $ahora = now();
        DB::table('config_franja_cita')->insert(
            collect(['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'])
                ->map(fn (string $dia) => [
                    'dia_semana' => $dia,
                    'hora_inicio' => '08:00:00',
                    'hora_fin' => '18:00:00',
                    'intervalo_minutos' => 30,
                    'activo' => true,
                    'actualizado_en' => $ahora,
                ])
                ->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('config_franja_cita');
    }
};
