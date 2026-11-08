<?php

use App\Enumerados\EstadoCita;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admite el estado «Confirmada» (HU-11.4) en la columna `cita.estado` de las
 * bases ya creadas. En MySQL/MariaDB la columna es un ENUM; SQLite (pruebas)
 * y las instalaciones nuevas ya lo traen desde la migración original.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->redefinirEstado(EstadoCita::valores());
    }

    public function down(): void
    {
        DB::table('cita')->where('estado', EstadoCita::Confirmada->value)->update(['estado' => EstadoCita::Asignada->value]);

        $this->redefinirEstado(array_values(array_diff(EstadoCita::valores(), [EstadoCita::Confirmada->value])));
    }

    /** @param  array<int, string>  $valores */
    private function redefinirEstado(array $valores): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) || ! Schema::hasColumn('cita', 'estado')) {
            return;
        }

        $lista = implode(',', array_map(fn (string $valor) => "'{$valor}'", $valores));
        $porDefecto = EstadoCita::Pendiente->value;

        DB::statement("ALTER TABLE `cita` MODIFY `estado` ENUM({$lista}) NOT NULL DEFAULT '{$porDefecto}'");
    }
};
